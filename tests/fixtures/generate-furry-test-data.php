<?php

declare(strict_types=1);

const ONLINESCHED_FIXTURE_DEFAULT_COUNT = 150;
const ONLINESCHED_FIXTURE_DEFAULT_START_ID = 4000;
const ONLINESCHED_FIXTURE_DEFAULT_DAYS = 3;
const ONLINESCHED_FIXTURE_DEFAULT_SEED = 20270630;
const ONLINESCHED_FIXTURE_ESSENTIALS_PER_DAY = 5;
const ONLINESCHED_FIXTURE_PRNG_MODULUS = 2147483647;
const ONLINESCHED_FIXTURE_PRNG_MULTIPLIER = 48271;

final class OnlineSchedFixturePrng {
	private int $state;

	public function __construct(int $seed) {
		$this->state = $seed;
	}

	public function next_int(int $minimum, int $maximum): int {
		$this->state = (int) (($this->state * ONLINESCHED_FIXTURE_PRNG_MULTIPLIER) % ONLINESCHED_FIXTURE_PRNG_MODULUS);

		return $minimum + ($this->state % (($maximum - $minimum) + 1));
	}

	public function chance(int $numerator, int $denominator): bool {
		return $this->next_int(1, $denominator) <= $numerator;
	}

	/**
	 * @param array<int, string> $values
	 */
	public function choose(array $values): string {
		return $values[$this->next_int(0, count($values) - 1)];
	}

	/**
	 * @param array<int, string> $values
	 * @return array<int, string>
	 */
	public function sample(array $values, int $count): array {
		for ($index = count($values) - 1; $index > 0; $index--) {
			$swap_index = $this->next_int(0, $index);
			$swap_value = $values[$index];
			$values[$index] = $values[$swap_index];
			$values[$swap_index] = $swap_value;
		}

		return array_slice($values, 0, $count);
	}
}

/**
 * @return array<string, mixed>
 */
function onlinesched_fixture_parse_arguments(array $arguments): array {
	$options = getopt('', array('output:', 'count:', 'start-id:', 'start-date:', 'days:', 'seed:', 'help'));

	if (isset($options['help'])) {
		onlinesched_fixture_usage(0);
	}


	foreach (array_slice($arguments, 1) as $argument) {
		if (!preg_match('/^--(?:output|count|start-id|start-date|days|seed)=.+$/', $argument) && $argument !== '--help') {
			onlinesched_fixture_fail('Only named options are supported.');
		}
	}

	if (!isset($options['start-date'])) {
		onlinesched_fixture_fail('--start-date=YYYY-MM-DD is required.');
	}

	$start_date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $options['start-date'], new DateTimeZone('UTC'));
	$errors = DateTimeImmutable::getLastErrors();
	if ($start_date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $start_date->format('Y-m-d') !== $options['start-date']) {
		onlinesched_fixture_fail('--start-date must be an ISO date in YYYY-MM-DD form.');
	}

	$count = onlinesched_fixture_positive_integer($options['count'] ?? ONLINESCHED_FIXTURE_DEFAULT_COUNT, '--count', 1, 10000);
	$start_id = onlinesched_fixture_positive_integer($options['start-id'] ?? ONLINESCHED_FIXTURE_DEFAULT_START_ID, '--start-id', 1, 2147483647);
	$days = onlinesched_fixture_positive_integer($options['days'] ?? ONLINESCHED_FIXTURE_DEFAULT_DAYS, '--days', 1, 7);
	$seed = onlinesched_fixture_positive_integer($options['seed'] ?? ONLINESCHED_FIXTURE_DEFAULT_SEED, '--seed', 1, ONLINESCHED_FIXTURE_PRNG_MODULUS - 1);
	$output = (string) ($options['output'] ?? (__DIR__ . '/generated/furry_test_data.csv'));
	if ($start_id > (2147483647 - ($count - 1))) {
		onlinesched_fixture_fail('--start-id plus --count exceeds the supported external ID range.');
	}

	if ($output === '' || is_dir($output)) {
		onlinesched_fixture_fail('--output must name a CSV file.');
	}

	return array(
		'count' => $count,
		'days' => $days,
		'output' => $output,
		'seed' => $seed,
		'start_date' => $start_date,
		'start_id' => $start_id,
	);
}

function onlinesched_fixture_positive_integer($value, string $name, int $minimum, int $maximum): int {
	if (!is_string($value) && !is_int($value)) {
		onlinesched_fixture_fail($name . ' must be an integer.');
	}

	$value = (string) $value;
	if (!preg_match('/^[0-9]+$/', $value)) {
		onlinesched_fixture_fail($name . ' must be an integer.');
	}

	$integer = (int) $value;
	if ($integer < $minimum || $integer > $maximum) {
		onlinesched_fixture_fail(sprintf('%s must be between %d and %d.', $name, $minimum, $maximum));
	}

	return $integer;
}

function onlinesched_fixture_fail(string $message): void {
	fwrite(STDERR, 'Error: ' . $message . "\n");
	exit(1);
}

function onlinesched_fixture_usage(int $status): void {
	$stream = $status === 0 ? STDOUT : STDERR;
	fwrite($stream, "Usage: php generate-furry-test-data.php --start-date=YYYY-MM-DD [--output=path] [--count=n] [--start-id=n] [--days=1..7] [--seed=n]\n");
	exit($status);
}

/**
 * @param array<string, mixed> $configuration
 * @return array<int, array<int, int|string>>
 */
function onlinesched_fixture_build_events(array $configuration): array {
	$rooms = array('Main Stage', 'Lakeshore', 'Greenway A', 'Greenway BCD (workshop)', 'Consuite', 'Dealers Den', 'Quiet Space', 'Art Show', 'Gaming Hall');
	$speakers = array('Kurst Hyperyote', 'Bandit Raccoon', 'Brushfox', 'Sly Coyote', 'Scribes McFluffington', 'Silver Husky', 'Night Wolf', 'Midnight Canine', 'TailWag Rex', 'Vixen Paint', 'Grizzly Gamer', 'Ozone Otter', 'Furry Migration Staff');
	$themes = array(
		'Fursuiting' => array(
			'titles' => array('Fursuit Walking and Performance', 'Fursuit Maintenance & Washing 101', 'Fursuit Maker Q&A', 'Fursuit Parade Staging', 'Fursuit Games', 'First-Time Fursuiter Meetup', 'Acting in Fursuit', 'Fursuit Photography Tips', 'Cooling Systems and Comfort', 'Making Fursuit Eyes & Teeth'),
			'description' => "Join our seasoned suiters to learn about the ins and outs of fursuiting, from construction to keeping cool.\n\nWhether you're a veteran fursuiter or putting on your first head, come hang out with the pack and share your tips!",
			'tags' => 'Fursuiting, Special Event',
		),
		'Art' => array(
			'titles' => array('Intro to Paw Art', 'Digital Anthro Illustration', 'Badge Painting Workshop', 'Anatomy for Furry Artists', 'Character Design Critiques', 'Commission Business Basics', 'Sketching Fur & Scales', 'Shading Masterclass', 'Furry Webcomics Q&A', 'Sculpting Your Fursona'),
			'description' => "An interactive session focusing on drawing and designing anthro characters. Bring your sketchbooks!\n\nWe will cover line art, shading, brush settings, and answer any questions from the audience.",
			'tags' => 'Art, Educational, Panel',
		),
		'Writing' => array(
			'titles' => array("Writing Your Fursona's Story", 'Worldbuilding for Anthro Fiction', 'Furry Publishing Panel', 'Creating Compelling Villains', "Show, Don't Tell in Furry Literature", 'Anthro Poetry Reading', 'Collaborative Storytelling', 'Writing Prompts & Speed Drills', 'Character Voice Workshop', 'Self-Publishing Your Novels'),
			'description' => "Explore the creative process of anthro literature. Learn how to outline, build worlds, and publish your work.\n\nParticipate in quick writing exercises and get live feedback from published authors in the fandom.",
			'tags' => 'Writing, Educational, Panel',
		),
		'Social' => array(
			'titles' => array('Opening Howl Ceremony', 'Coyote vs Raccoon Dance-Off', 'Species Meetup: Canines', 'Species Meetup: Felines', 'Species Meetup: Avian & Reptile', 'Furry Migration Board Games', 'First Con Q&A', 'Charity Auction for Wildlife', 'Closing Howl and Dead Dog', 'Quiet Paws Chill Space'),
			'description' => "Meet new friends, hang out, and share some laughs. Everyone is welcome to participate!\n\nA great place for attendees to meet new friends, trade badges, or just take a break from the main convention floor.",
			'tags' => 'Social, Special Event',
		),
		'Music' => array(
			'titles' => array('Anthro DJ Live Set: Remmy', 'Dead Dog Dance: Otto', 'Dead Dog Dance: Okrahound', 'Electronic Music Production', 'Intro to Synthwave & Chiptunes', 'Furry Karaoke Night', 'Acoustic Jam Session', 'Sound Design for Games', 'Bass and Beats Workshop', 'Late Night Dance Party'),
			'description' => "Genre: Groovy Techno, House & Electro\n\nImmerse yourself in the sounds of the fandom. Learn from active DJs and producers or just enjoy the heavy basslines on the Lakeshore dancefloor!",
			'tags' => 'Dances',
		),
		'Gaming' => array(
			'titles' => array('D&D One-Shot: The Lost Bone', 'Retro Console Free Play', 'Super Smash Bork Tournament', 'Tabletop Strategy Meetup', 'LARP Character Creation', 'Trading Card Open Play', 'Speedrunning for Charity', 'Indie Game Showcases', 'Dice-Rolling Clinic', "Dragon's Hoard Raid Night"),
			'description' => "Compete, cooperate, and play! Whether you prefer cardboard, dice, or pixels, we have a seat for you.\n\nEquipment and game boards provided on site.",
			'tags' => 'Gaming, Competition',
		),
	);
	$cancelled_titles = array('Napping in the Raccoon Lounge', 'Snack Thieves Recovery Panel', 'Tail Styling Disasters Panel', 'Emergency Floof Styling Session', 'The Great Treats Heist Investigation');
	$prng = new OnlineSchedFixturePrng($configuration['seed']);
	$events = array();
	$base_per_day = intdiv($configuration['count'], $configuration['days']);
	$remainder = $configuration['count'] % $configuration['days'];
	$id = $configuration['start_id'];

	for ($day_index = 0; $day_index < $configuration['days']; $day_index++) {
		$event_count = $base_per_day + ($day_index < $remainder ? 1 : 0);
		$date = $configuration['start_date']->modify('+' . $day_index . ' days');
		$start_hour = $day_index === ($configuration['days'] - 1) ? 9 : 9;
		$end_hour = $day_index === ($configuration['days'] - 1) ? 17 : 23;

		for ($event_index = 0; $event_index < $event_count; $event_index++, $id++) {
			$is_cancelled = $prng->chance(10, 100);
			$hour = $prng->next_int($start_hour, $end_hour);
			$minute = $prng->choose(array('00', '15', '30', '45'));
			$room = $prng->choose($rooms);
			$speaker_count = $prng->next_int(0, 3);
			$speaker_names = $prng->sample($speakers, $speaker_count);
			$length = $prng->choose(array('45', '60', '90', '120'));

			if ($is_cancelled) {
				$name = $prng->choose($cancelled_titles) . ' (Cancelled Event #' . $id . ')';
				$description = 'This event has been cancelled due to unforeseen scheduling conflicts. Raccoons or other mischievous critters may have been involved.';
				$tags = 'Cancelled';
			} else {
				$category = $prng->choose(array_keys($themes));
				$theme = $themes[$category];
				$name = $prng->choose($theme['titles']) . ' (Event #' . $id . ')';
				$description = $theme['description'] . ' Sponsored by the ' . $category . ' department.';
				$tags = $theme['tags'];

				if ($hour >= 21 && $prng->chance(30, 100)) {
					$tags .= ', Adult';
					$name = '[18+] ' . $name;
					$description .= ' Restricted to attendees 18 years and older.';
				}

				if ($prng->chance(5, 100)) {
					$tags .= ', VIP';
					$name = '[VIP] ' . $name;
				}
			}

			$events[] = array($id, $name, $date->format('Y-m-d'), sprintf('%02d:%s:00', $hour, $minute), $description, $room, implode(', ', $speaker_names), $length, $tags);
		}
	}

	onlinesched_fixture_apply_anchors($events, $configuration);
	onlinesched_fixture_apply_essential_anchors($events, $configuration);

	return $events;
}

/**
 * @param array<int, array<int, int|string>> $events
 * @param array<string, mixed> $configuration
 */
function onlinesched_fixture_apply_anchors(array &$events, array $configuration): void {
	$anchors = array(
		126 => array(
			'name' => 'Sound Design for Games',
			'room' => 'Quiet Space',
			'speakers' => 'Brushfox, Midnight Canine',
			'length' => '120',
			'tags' => 'Dances',
			'time' => '17:45:00',
		),
		131 => array(
			'name' => 'Sculpting Your Fursona',
			'description' => 'Coyote Stuff: tools, textures, and finishing techniques for a personal fursona sculpture.',
			'room' => 'Dealers Den',
			'speakers' => 'Vixen Paint',
			'length' => '90',
			'tags' => 'Art, Educational, Panel',
			'time' => '16:00:00',
		),
	);

	foreach ($anchors as $offset => $anchor) {
		if (!isset($events[$offset])) {
			continue;
		}

		$events[$offset][1] = $anchor['name'] . ' (Event #' . $events[$offset][0] . ')';
		$events[$offset][3] = $anchor['time'];
		$events[$offset][5] = $anchor['room'];
		$events[$offset][6] = $anchor['speakers'];
		$events[$offset][7] = $anchor['length'];
		$events[$offset][8] = $anchor['tags'];
		if (isset($anchor['description'])) {
			$events[$offset][4] = $anchor['description'];
		}
	}

	$unscheduled_offset = 0;
	if ($unscheduled_offset >= 0 && isset($events[$unscheduled_offset])) {
		$events[$unscheduled_offset][1] = 'Volunteer Check-In (Unscheduled Event #' . $events[$unscheduled_offset][0] . ')';
		$events[$unscheduled_offset][2] = '';
		$events[$unscheduled_offset][3] = '';
		$events[$unscheduled_offset][4] = "Schedule this volunteer check-in when staffing is confirmed.\n\nIt intentionally covers the OnlineSched unscheduled CSV path.";
		$events[$unscheduled_offset][5] = '';
		$events[$unscheduled_offset][6] = 'Furry Migration Staff';
		$events[$unscheduled_offset][7] = '45';
		$events[$unscheduled_offset][8] = 'Volunteer, Unscheduled';
	}
}

/**
 * @param array<int, array<int, int|string>> $events
 * @param array<string, mixed> $configuration
 */
function onlinesched_fixture_apply_essential_anchors(array &$events, array $configuration): void {
	$titles = array(
		'Convention Essentials Briefing',
		'Guest of Honor Meet and Greet',
		'Special Guest Spotlight',
		'Fursuit Parade',
		'Charity Auction for Wildlife',
	);
	$times = array('09:30:00', '11:00:00', '13:00:00', '14:30:00', '16:00:00');
	$rooms = array('Main Stage', 'Lakeshore', 'Greenway A', 'Dealers Den', 'Consuite');
	$secondary_tags = array('Guest Of Honor', 'Special Guest', 'VIP');

	for ($day_index = 0; $day_index < $configuration['days']; $day_index++) {
		$date = $configuration['start_date']->modify('+' . $day_index . ' days')->format('Y-m-d');
		$essential_index = 0;

		foreach ($events as $event_index => $event) {
			if ($event[2] !== $date) {
				continue;
			}

			$title = $titles[$essential_index];
			if ($essential_index === 0 && $day_index === 0) {
				$title = 'Opening Howl Ceremony';
			} elseif ($essential_index === 0 && $day_index === ($configuration['days'] - 1)) {
				$title = 'Closing Howl and Dead Dog';
			}

			$secondary_tag = $secondary_tags[($day_index + $essential_index) % count($secondary_tags)];
			$event_id = $events[$event_index][0];
			$events[$event_index][1] = $title . ' (Event #' . $event_id . ')';
			$events[$event_index][3] = $times[$essential_index];
			$events[$event_index][4] = 'A featured convention event included in the Essentials schedule for attendee planning and testing.';
			$events[$event_index][5] = $rooms[$essential_index];
			$events[$event_index][6] = 'Furry Migration Staff';
			$events[$event_index][7] = '60';
			$events[$event_index][8] = 'Essentials, ' . $secondary_tag;
			$essential_index++;

			if ($essential_index === ONLINESCHED_FIXTURE_ESSENTIALS_PER_DAY) {
				break;
			}
		}
	}
}

/**
 * @param array<string, mixed> $configuration
 * @param array<int, array<int, int|string>> $events
 */
function onlinesched_fixture_write_csv(array $configuration, array $events): void {
	$directory = dirname($configuration['output']);
	if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
		onlinesched_fixture_fail('Unable to create output directory: ' . $directory);
	}

	if (!is_writable($directory)) {
		onlinesched_fixture_fail('Output directory is not writable: ' . $directory);
	}
	if (file_exists($configuration['output']) && !is_writable($configuration['output'])) {
		onlinesched_fixture_fail('Output file is not writable: ' . $configuration['output']);
	}

	$temporary_output = $configuration['output'] . '.tmp-' . getmypid();
	$handle = @fopen($temporary_output, 'xb');
	if ($handle === false) {
		onlinesched_fixture_fail('Unable to create temporary output file for: ' . $configuration['output']);
	}

	try {
		if (fputcsv($handle, array('ID', 'Name', 'Date', 'Time', 'Description', 'Room_Type', 'Speakers', 'Length', 'Tags'), ',', '"', '', "\n") === false) {
			throw new RuntimeException('Unable to write CSV header.');
		}

		foreach ($events as $event) {
			if (fputcsv($handle, $event, ',', '"', '', "\n") === false) {
				throw new RuntimeException('Unable to write CSV event row.');
			}
		}
	} catch (Throwable $exception) {
		fclose($handle);
		@unlink($temporary_output);
		onlinesched_fixture_fail($exception->getMessage());
	}

	if (!fclose($handle)) {
		@unlink($temporary_output);
		onlinesched_fixture_fail('Unable to close output file.');
	}

	if (!rename($temporary_output, $configuration['output'])) {
		@unlink($temporary_output);
		onlinesched_fixture_fail('Unable to move generated fixture to: ' . $configuration['output']);
	}
}

/**
 * @param array<string, mixed> $configuration
 */
function onlinesched_fixture_print_summary(array $configuration): void {
	$end_date = $configuration['start_date']->modify('+' . ($configuration['days'] - 1) . ' days');
	fwrite(STDOUT, 'Output: ' . $configuration['output'] . "\n");
	fwrite(STDOUT, 'Events: ' . $configuration['count'] . "\n");
	fwrite(STDOUT, 'External ID range: ' . $configuration['start_id'] . '-' . ($configuration['start_id'] + $configuration['count'] - 1) . "\n");
	fwrite(STDOUT, 'Start date: ' . $configuration['start_date']->format('Y-m-d') . "\n");
	fwrite(STDOUT, 'End date: ' . $end_date->format('Y-m-d') . "\n");
	fwrite(STDOUT, 'Days: ' . $configuration['days'] . "\n");
	fwrite(STDOUT, 'Seed: ' . $configuration['seed'] . "\n");
}

$configuration = onlinesched_fixture_parse_arguments($argv);
$events = onlinesched_fixture_build_events($configuration);
onlinesched_fixture_write_csv($configuration, $events);
onlinesched_fixture_print_summary($configuration);
