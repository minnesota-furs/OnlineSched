<?php
/**
 * Comment-style gate.
 *
 * Scans first-party PHP, JS, CSS and SCSS for the comment rules in AGENTS.md:
 * no requirement IDs, no attribution or process notes, ASCII only, a two-line
 * cap on prose comments, and limits on comment density. Structured docblocks
 * are exempt from the length cap only; every other rule still applies to them.
 *
 * Usage: php tools/check-comments.php [path ...]
 * Exits non-zero and lists file:line for every violation.
 */

const MAX_BLOCK_LINES = 2;
const MAX_COMMENT_RATIO = 0.22;
const MIN_RATIO_FILE_LINES = 60;
const MAX_COMMENTED_DECL_RUN = 3;

// Generated output and vendored third-party drops. fb/ is fancyBox and
// src/js/packery.js is Packery; both carry upstream copyright.
const SKIP_PATTERNS = [
    '#/node_modules/#',
    '#/vendor/#',
    '#/dist/#',
    '#/build/#',
    '#/playwright-report/#',
    '#/test-results/#',
    '#\.min\.(js|css)$#',
    '#\.pack\.js$#',
    '#/fb/#',
    '#/src/js/packery\.js$#',
    '#/src/js/masonry\.js$#',
    '#/src/js/imagesloaded\.js$#',
];

const REQUIREMENT_ID = '/\bR-\d{3}\b/';

// Attribution, history and process. Each pattern names a thing a comment must
// not record; git blame and the PR hold all of it.
const PERSONAL_PATTERNS = [
    'attribution'  => '/\b(Kaiser|Diesel|Sledge|Magnus)\b/i',
    'owner-call'   => '/\bowner(\s+(call|decision|rule|feedback|cap|approved))\b/i',
    'date-stamp'   => '/\b20\d{2}-\d{2}-\d{2}\b/',
    'process-note' => '/(\bpass (one|two|three)\b|\b(it|this|that|which|we|they|there)\s+used\s+to\b|\bused\s+to\s+be\b|(^|[.;]\s+)previously\b|\bfor now\b|\bflagged for later\b|\bround \d|\bphase \d)/i',
    'review-note'  => '/\b(review|feedback|finding)\s*#?\d+/i',
];

// Package metadata WordPress and the docblock tooling actually read. Authorship
// and versions belong in these; the rules above do not apply to them.
const METADATA_LINE = '~^\s*[*#/\s]*(?:@(?:author|copyright|license|since|version|package|link|see|deprecated)\b'
    . '|(?:Plugin Name|Plugin URI|Theme Name|Theme URI|Author|Author URI|Version|Description'
    . '|License|License URI|Text Domain|Domain Path|Requires at least|Requires PHP'
    . '|Tested up to|Update URI|Template|Tags|Stable tag|Contributors)\s*:)~i';

// A line that maps a byte or entity to the character it produces. The character
// in the comment IS the documentation, so the ASCII rule cannot apply.
const CHARACTER_TABLE_LINE = '~(chr\(\d+\)|\\\\x[0-9A-Fa-f]{2}|&[a-zA-Z]+;|&\#\d+;|U\+[0-9A-Fa-f]{4})~';

/** One rule violation, rendered as a single reportable line. */
final class Violation
{
    public function __construct(
        public string $file,
        public int $line,
        public string $rule,
        public string $detail
    ) {
    }

    public function __toString(): string
    {
        return "{$this->file}:{$this->line}  [{$this->rule}] {$this->detail}";
    }
}

/**
 * One comment found in a source file.
 *
 * @param bool $doc True for a structured docblock, which the length cap skips.
 */
final class CommentSpan
{
    public function __construct(
        public int $startLine,
        public int $endLine,
        public string $text,
        public bool $doc,
        public bool $ownLine,
        public int $offset = -1
    ) {
    }
}

/** Locates every comment in [$source], using the real tokenizer for PHP. */
function comment_spans(string $path, string $source): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $spans = $ext === 'php'
        ? php_comment_spans($source)
        : brace_comment_spans($source, $ext !== 'css');
    return merge_adjacent($spans);
}

/** Folds consecutive own-line line-comments into one block, as a reader sees it. */
function merge_adjacent(array $spans): array
{
    $merged = [];
    foreach ($spans as $span) {
        $last = $merged ? $merged[count($merged) - 1] : null;
        $joinable = $last
            && !$last->doc
            && !$span->doc
            && $last->ownLine
            && $span->ownLine
            && $span->startLine === $last->endLine + 1;
        if ($joinable) {
            $last->endLine = $span->endLine;
            $last->text .= "\n" . $span->text;
            continue;
        }
        $merged[] = $span;
    }
    return $merged;
}

function php_comment_spans(string $source): array
{
    $spans = [];
    $tokens = @token_get_all($source);
    $offset = 0;
    $lineStarts = line_start_offsets($source);
    foreach ($tokens as $token) {
        $text = is_array($token) ? $token[1] : $token;
        if (is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
            $line = $token[2];
            $trimmed = rtrim($text, "\r\n");
            $spans[] = new CommentSpan(
                $line,
                $line + substr_count($trimmed, "\n"),
                $trimmed,
                $token[0] === T_DOC_COMMENT,
                starts_line($source, $line, $lineStarts),
                $offset
            );
        }
        $offset += strlen($text);
    }
    return $spans;
}

/** Byte offset of the first character of every line, one-indexed by line. */
function line_start_offsets(string $source): array
{
    $starts = [1 => 0];
    $line = 1;
    $length = strlen($source);
    for ($i = 0; $i < $length; $i++) {
        if ($source[$i] === "\n") {
            $starts[++$line] = $i + 1;
        }
    }
    return $starts;
}

/** True when [$line] holds nothing but the comment that starts on it. */
function starts_line(string $source, int $line, ?array $lineStarts = null): bool
{
    $start = $lineStarts[$line] ?? null;
    if ($start === null) {
        $lines = explode("\n", $source);
        $text = $lines[$line - 1] ?? '';
    } else {
        $end = strpos($source, "\n", $start);
        $text = substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
    }
    return (bool) preg_match('~^\s*(//|\#|/\*)~', $text);
}

/** Scans a brace-syntax file. [$lineComments] is false for plain CSS. */
function brace_comment_spans(string $source, bool $lineComments): array
{
    $spans = [];
    $length = strlen($source);
    $line = 1;
    $i = 0;
    $prevSignificant = '';

    while ($i < $length) {
        $ch = $source[$i];
        $next = $source[$i + 1] ?? '';

        if ($ch === "\n") {
            $line++;
            $i++;
            continue;
        }

        if ($ch === '/' && $next === '*') {
            $end = strpos($source, '*/', $i + 2);
            $end = $end === false ? $length : $end + 2;
            $text = substr($source, $i, $end - $i);
            $spans[] = new CommentSpan(
                $line,
                $line + substr_count($text, "\n"),
                $text,
                str_starts_with($text, '/**'),
                line_is_only_comment($source, $i),
                $i
            );
            $line += substr_count($text, "\n");
            $i = $end;
            continue;
        }

        if ($lineComments && $ch === '/' && $next === '/') {
            $end = strpos($source, "\n", $i);
            $end = $end === false ? $length : $end;
            $spans[] = new CommentSpan(
                $line,
                $line,
                substr($source, $i, $end - $i),
                false,
                line_is_only_comment($source, $i),
                $i
            );
            $i = $end;
            continue;
        }

        if ($ch === '"' || $ch === "'" || $ch === '`') {
            $i = skip_string($source, $i, $ch, $line);
            $prevSignificant = $ch;
            continue;
        }

        if ($ch === '/' && $lineComments && regex_can_start($prevSignificant)) {
            $i = skip_regex($source, $i, $line);
            $prevSignificant = '/';
            continue;
        }

        if (trim($ch) !== '') {
            $prevSignificant = $ch;
        }
        $i++;
    }

    return $spans;
}

function line_is_only_comment(string $source, int $offset): bool
{
    $start = strrpos(substr($source, 0, $offset), "\n");
    $start = $start === false ? 0 : $start + 1;
    return trim(substr($source, $start, $offset - $start)) === '';
}

function skip_string(string $source, int $i, string $quote, int &$line): int
{
    $length = strlen($source);
    $i++;
    while ($i < $length) {
        if ($source[$i] === '\\') {
            $i += 2;
            continue;
        }
        if ($source[$i] === "\n") {
            $line++;
        }
        if ($source[$i] === $quote) {
            return $i + 1;
        }
        $i++;
    }
    return $length;
}

/** A slash after these starts a regex literal rather than a division. */
function regex_can_start(string $prev): bool
{
    return $prev === '' || str_contains('(,=:[!&|?{};+-*%~^<>', $prev);
}

function skip_regex(string $source, int $i, int &$line): int
{
    $length = strlen($source);
    $i++;
    $inClass = false;
    while ($i < $length) {
        $ch = $source[$i];
        if ($ch === '\\') {
            $i += 2;
            continue;
        }
        if ($ch === "\n") {
            $line++;
            return $i;
        }
        if ($ch === '[') {
            $inClass = true;
        } elseif ($ch === ']') {
            $inClass = false;
        } elseif ($ch === '/' && !$inClass) {
            return $i + 1;
        }
        $i++;
    }
    return $length;
}

const DECLARATION_PATTERN = '/^\s*(?:'
    . '(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|trait|enum)\s+\w'
    . '|(?:public\s+|private\s+|protected\s+|static\s+|final\s+|abstract\s+)*'
    . 'function\s+&?\w+\s*\('
    . '|(?:public\s+|private\s+|protected\s+)+(?:static\s+)?(?:readonly\s+)?'
    . '(?:\??[\w\\\\|]+\s+)?\$\w+'
    . '|(?:public\s+|private\s+|protected\s+)*const\s+\w+'
    . '|(?:export\s+)?(?:async\s+)?function\s+\w+\s*\('
    . '|(?:export\s+)?(?:const|let|var)\s+\w+\s*='
    . '|define\s*\(\s*[\'"]'
    . ')/';

function looks_like_declaration(string $line): bool
{
    return (bool) preg_match(DECLARATION_PATTERN, $line);
}

function check_file(string $path, string $source): array
{
    $violations = [];
    $lines = explode("\n", $source);
    $spans = comment_spans($path, $source);

    foreach ($spans as $span) {
        foreach (explode("\n", $span->text) as $offset => $text) {
            $at = $span->startLine + $offset;
            if (preg_match(REQUIREMENT_ID, $text, $m)) {
                $violations[] = new Violation($path, $at, 'requirement-id', $m[0]);
            }
            $sourceLine = $lines[$at - 1] ?? $text;
            if (
                preg_match('/[^\x00-\x7F]/u', $text, $m)
                && !preg_match(CHARACTER_TABLE_LINE, $sourceLine)
            ) {
                $violations[] = new Violation(
                    $path,
                    $at,
                    'non-ascii',
                    'U+' . strtoupper(dechex(mb_ord($m[0]))) . ' in comment'
                );
            }
            if (preg_match(METADATA_LINE, $text)) {
                continue;
            }
            foreach (PERSONAL_PATTERNS as $rule => $pattern) {
                if (preg_match($pattern, $text, $m)) {
                    $violations[] = new Violation($path, $at, $rule, trim($m[0]));
                }
            }
        }
        $span_lines = $span->endLine - $span->startLine + 1;
        $metadata = (bool) preg_match(METADATA_LINE, $span->text);
        if (
            !$span->doc
            && !$metadata
            && !is_banner($span->text)
            && $span->ownLine
            && $span_lines > MAX_BLOCK_LINES
        ) {
            $violations[] = new Violation($path, $span->startLine, 'block-length', "{$span_lines} lines");
        }
    }

    foreach ($lines as $index => $line) {
        if (!preg_match(REQUIREMENT_ID, $line, $m)) {
            continue;
        }
        if (line_in_span($spans, $index + 1)) {
            continue;
        }
        $violations[] = new Violation($path, $index + 1, 'requirement-id', $m[0] . ' outside a comment');
    }

    return array_merge($violations, density_violations($path, $lines, $spans));
}

/**
 * True for a section divider: rule lines around one short title.
 *
 * A banner reads as one thing, so the length cap would only force it to be
 * uglier without removing any prose.
 */
function is_banner(string $text): bool
{
    $content = [];
    foreach (explode("\n", $text) as $line) {
        $line = trim(preg_replace('~^\s*(//+|\#+|/?\*+/?)~', '', $line));
        $line = trim($line, "=-*_~ \t");
        if ($line !== '') {
            $content[] = $line;
        }
    }
    return count($content) <= 2;
}

function line_in_span(array $spans, int $line): bool
{
    foreach ($spans as $span) {
        if ($line >= $span->startLine && $line <= $span->endLine) {
            return true;
        }
    }
    return false;
}

function density_violations(string $path, array $lines, array $spans): array
{
    $violations = [];

    // Docblocks and package metadata are documentation, not prose noise, so the
    // ratio measures only the comments the length cap governs.
    $commentLines = [];
    foreach ($spans as $span) {
        if ($span->doc || preg_match(METADATA_LINE, $span->text)) {
            continue;
        }
        for ($n = $span->startLine; $n <= $span->endLine; $n++) {
            $commentLines[$n] = true;
        }
    }
    $nonBlank = 0;
    foreach ($lines as $line) {
        if (trim($line) !== '') {
            $nonBlank++;
        }
    }

    if ($nonBlank >= MIN_RATIO_FILE_LINES) {
        $ratio = count($commentLines) / $nonBlank;
        if ($ratio > MAX_COMMENT_RATIO) {
            $violations[] = new Violation(
                $path,
                1,
                'comment-ratio',
                sprintf('%.1f%% of %d lines (limit %d%%)', $ratio * 100, $nonBlank, MAX_COMMENT_RATIO * 100)
            );
        }
    }

    // Only single-line comments count toward a run. A wall of one-liners is the
    // over-commenting smell; a documented API writes more.
    $commentedDecl = [];
    foreach ($spans as $span) {
        if ($span->startLine !== $span->endLine || !$span->ownLine) {
            continue;
        }
        $next = $span->endLine + 1;
        while (isset($lines[$next - 1]) && trim($lines[$next - 1]) === '') {
            $next++;
        }
        if (isset($lines[$next - 1]) && looks_like_declaration($lines[$next - 1])) {
            $commentedDecl[$next] = true;
        }
    }

    $declarations = [];
    foreach ($lines as $index => $line) {
        if (!isset($commentLines[$index + 1]) && looks_like_declaration($line)) {
            $declarations[] = $index + 1;
        }
    }

    $run = [];
    foreach ($declarations as $decl) {
        if (isset($commentedDecl[$decl])) {
            $run[] = $decl;
            continue;
        }
        report_run($violations, $path, $run);
        $run = [];
    }
    report_run($violations, $path, $run);

    return $violations;
}

function report_run(array &$violations, string $path, array $run): void
{
    if (count($run) > MAX_COMMENTED_DECL_RUN) {
        $violations[] = new Violation(
            $path,
            $run[0],
            'comment-density',
            count($run) . ' commented declarations in a row'
        );
    }
}

function should_skip(string $path): bool
{
    foreach (SKIP_PATTERNS as $pattern) {
        if (preg_match($pattern, '/' . ltrim($path, './'))) {
            return true;
        }
    }
    return false;
}

function collect(string $target): array
{
    if (is_file($target)) {
        return should_skip($target) ? [] : [$target];
    }
    if (!is_dir($target)) {
        fwrite(STDERR, "check-comments: no such path: {$target}\n");
        exit(2);
    }
    $found = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        if (!preg_match('/\.(php|js|css|scss)$/', $path) || should_skip($path)) {
            continue;
        }
        $found[] = $path;
    }
    sort($found);
    return $found;
}

// Loaded as a library by the tests and the comment-strip prover; only the
// direct invocation runs the scan.
if (!isset($argv) || realpath($argv[0] ?? '') !== realpath(__FILE__)) {
    return;
}

$targets = array_slice($argv, 1);
if (!$targets) {
    $targets = ['.'];
}

$violations = [];
foreach ($targets as $target) {
    foreach (collect($target) as $path) {
        $violations = array_merge($violations, check_file($path, file_get_contents($path)));
    }
}

if (!$violations) {
    echo "check-comments: clean\n";
    exit(0);
}

$counts = [];
foreach ($violations as $violation) {
    echo $violation . "\n";
    $counts[$violation->rule] = ($counts[$violation->rule] ?? 0) + 1;
}
echo "\ncheck-comments: " . count($violations) . " violations\n";
foreach ($counts as $rule => $count) {
    echo "  {$rule}: {$count}\n";
}
exit(1);
