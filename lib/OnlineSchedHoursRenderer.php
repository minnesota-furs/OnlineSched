<?php
/**
 * Hours renderer for OnlineSched blocks.
 *
 * @package    OnlineSched
 * @author     BL, BM, AL & Contributors
 * @copyright  2016-2026 Original Authors
 * @license    GPL-2.0-or-later
 *
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

}
