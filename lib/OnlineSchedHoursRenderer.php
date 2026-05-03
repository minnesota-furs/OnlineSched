<?php
/**
 * Hours renderer for OnlineSched blocks and migrations.
 *
 * @package    OnlineSched
 * @author     BL, BM, AL & Contributors
 * @copyright  2016-2026 Original Authors
 * @license    GPL-2.0-or-later
 *
 * Revised by: Kurst Hyperyote for Furry Migration
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnlineSchedHoursRenderer
{
    public static function render_wrapper($attributes, $content, $block = null)
    {
        return '<div class="os-hours"><div class="os-hours__row">' . $content . '</div></div>';
    }

    public static function render_department($attributes, $content, $block = null)
    {
        $department = sanitize_text_field($attributes['department'] ?? '');
        $location = wp_kses_post($attributes['location'] ?? '');

        if ('' === trim($department . wp_strip_all_tags($location) . wp_strip_all_tags($content))) {
            return '';
        }

        $html = '<section class="os-hours__dept">';
        if ($department !== '') {
            $html .= '<h3 class="os-hours__name">' . esc_html($department) . '</h3>';
        }
        if ($location !== '') {
            $html .= '<div class="os-hours__location">' . $location . '</div>';
        }
        $html .= '<dl class="os-hours__days">' . $content . '</dl>';
        $html .= '</section>';

        return $html;
    }

    public static function render_day($attributes, $content, $block = null)
    {
        $day = sanitize_text_field($attributes['day'] ?? '');

        if ($day === '' && trim(wp_strip_all_tags($content)) === '') {
            return '';
        }

        return '<dt>' . esc_html($day) . '</dt><dd>' . $content . '</dd>';
    }

    public static function render_time($attributes, $content = '', $block = null)
    {
        $hours = sanitize_text_field($attributes['hours'] ?? '');
        $small_text = sanitize_text_field($attributes['smallText'] ?? '');
        $add_break = !empty($attributes['addBreak']);
        $italics = array_map('sanitize_text_field', (array) ($attributes['italics'] ?? array()));

        if ($hours === '' && $small_text === '') {
            return '';
        }

        $hours_html = esc_html($hours);
        if (in_array('Hours', $italics, true)) {
            $hours_html = '<em>' . $hours_html . '</em>';
        }

        $small_html = '';
        if ($small_text !== '') {
            $small_inner = esc_html($small_text);
            if (in_array('Small', $italics, true)) {
                $small_inner = '<em>' . $small_inner . '</em>';
            }
            $small_class = $add_break ? ' os-hours__time-small--break' : '';
            $small_html = '<small class="os-hours__time-small' . esc_attr($small_class) . '">' . $small_inner . '</small>';
        }

        $content = $hours_html;
        if ($small_html !== '') {
            $content .= $add_break ? $small_html : ' ' . $small_html;
        }

        return '<span class="os-hours__time">' . $content . '</span>';
    }

    public static function build_block_markup_from_acf(array $departments)
    {
        $department_blocks = array();

        foreach ($departments as $department) {
            if (!is_array($department)) {
                continue;
            }

            $day_blocks = array();
            foreach ((array) ($department['days'] ?? array()) as $day) {
                if (!is_array($day)) {
                    continue;
                }

                $time_blocks = array();
                foreach ((array) ($day['times'] ?? array()) as $time) {
                    if (!is_array($time)) {
                        continue;
                    }

                    $hours = sanitize_text_field(self::normalize_import_text($time['hours'] ?? ''));
                    $small_text = sanitize_text_field(self::normalize_import_text($time['small_text'] ?? ''));
                    if ($hours === '' && $small_text === '') {
                        continue;
                    }

                    $time_blocks[] = self::block('onlinesched/hours-time', array(
                        'hours'     => $hours,
                        'smallText' => $small_text,
                        'addBreak'  => !empty($time['add_br']),
                        'italics'   => array_values(array_map('sanitize_text_field', (array) ($time['italics'] ?? array()))),
                    ));
                }

                if (empty($time_blocks)) {
                    continue;
                }

                $day_blocks[] = self::block('onlinesched/hours-day', array(
                    'day' => sanitize_text_field(self::normalize_import_text($day['day'] ?? '')),
                ), $time_blocks);
            }

            $department_name = sanitize_text_field(self::normalize_import_text($department['department'] ?? ''));
            // ACF WYSIWYG wraps the location in <p> tags. Strip them so the block
            // attribute stores clean text, not HTML that would round-trip through
            // JSON encoding and render as literal escape sequences.
            $location = trim(wp_strip_all_tags(self::normalize_import_text($department['location'] ?? '')));
            if ($department_name === '' && $location === '' && empty($day_blocks)) {
                continue;
            }

            $department_blocks[] = self::block('onlinesched/hours-department', array(
                'department' => $department_name,
                'location'   => $location,
            ), $day_blocks);
        }

        return serialize_block(self::block('onlinesched/hours-of-operations', array(), $department_blocks));
    }

    private static function block($name, array $attributes = array(), array $inner_blocks = array())
    {
        return array(
            'blockName'    => $name,
            'attrs'        => $attributes,
            'innerBlocks'  => $inner_blocks,
            'innerHTML'    => '',
            'innerContent' => array_fill(0, count($inner_blocks), null),
        );
    }

    private static function normalize_import_text($value)
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace_callback('/\\\\?u([0-9a-fA-F]{4})/', function ($matches) {
            return html_entity_decode('&#x' . $matches[1] . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $value);
    }
}
