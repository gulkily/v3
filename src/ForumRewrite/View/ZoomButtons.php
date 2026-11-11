<?php

declare(strict_types=1);

namespace ForumRewrite\View;

/**
 * The site's text-size buttons (Smaller, Larger, and for tables Fit and Actual size): icons,
 * labels and the shared .zoom-buttons group (site.css). Used by the Query viewer, the Forte toolbar and tables
 * in Markdown posts, so all three look and read the same. Each place keeps its own data
 * attribute and script; the stepping code is shared separately (ZenMemes thread
 * thread-20261110231618-168b822f). Every button carries .zoom-button so quick repeated taps
 * aren't read as a browser double-tap zoom (site.css).
 */
final class ZoomButtons
{
    private const ICONS = [
        'smaller' => '<circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor"/>'
            . '<line x1="10.2" y1="10.2" x2="14" y2="14" stroke="currentColor"/>'
            . '<line x1="5" y1="7" x2="9" y2="7" stroke="currentColor"/>',
        'larger' => '<circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor"/>'
            . '<line x1="10.2" y1="10.2" x2="14" y2="14" stroke="currentColor"/>'
            . '<line x1="5" y1="7" x2="9" y2="7" stroke="currentColor"/>'
            . '<line x1="7" y1="5" x2="7" y2="9" stroke="currentColor"/>',
        // Two walls with a double arrow between them: stretch to the screen's width.
        'fit' => '<line x1="1.5" y1="3" x2="1.5" y2="13" stroke="currentColor"/>'
            . '<line x1="14.5" y1="3" x2="14.5" y2="13" stroke="currentColor"/>'
            . '<line x1="4" y1="8" x2="12" y2="8" stroke="currentColor"/>'
            . '<polyline points="6,6 4,8 6,10" fill="none" stroke="currentColor"/>'
            . '<polyline points="10,6 12,8 10,10" fill="none" stroke="currentColor"/>',
        // "1:1", as in image viewers: the same size as the page's own text.
        'actual' => '<polyline points="2.5,5 4.5,3.5 4.5,12.5" fill="none" stroke="currentColor"/>'
            . '<circle cx="8" cy="6.5" r="0.9" fill="currentColor"/>'
            . '<circle cx="8" cy="10" r="0.9" fill="currentColor"/>'
            . '<polyline points="11.5,5 13.5,3.5 13.5,12.5" fill="none" stroke="currentColor"/>',
    ];

    private const LABELS = [
        'smaller' => 'Smaller',
        'larger' => 'Larger',
        'fit' => 'Fit to screen',
        'actual' => 'Same size as page text',
    ];

    /** Group order: Smaller, then the optional Fit and Actual size, then Larger. */
    private const ORDER = ['smaller', 'fit', 'actual', 'larger'];

    /**
     * One icon button: "smaller", "larger", "fit" or "actual". $attribute is the data attribute
     * the page's script listens for (e.g. "data-query-zoom"); $class is any extra class.
     */
    public static function button(string $kind, string $attribute, string $value, string $class = ''): string
    {
        if (!isset(self::ICONS[$kind])) {
            throw new \InvalidArgumentException('Zoom button must be smaller, larger, fit or actual.');
        }
        $label = self::LABELS[$kind];
        return '<button type="button" class="zoom-button' . ($class !== '' ? ' ' . self::escape($class) : '') . '"'
            . ' ' . self::attributeName($attribute) . '="' . self::escape($value) . '"'
            . ' aria-label="' . $label . '" title="' . $label . '">'
            . '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">' . self::ICONS[$kind] . '</svg></button>';
    }

    /**
     * A labelled group. $values maps "smaller" and "larger", and optionally "fit" and "actual",
     * to the data attribute's values; buttons appear as Smaller, Fit, Actual size, Larger.
     *
     * @param array{smaller: string, larger: string, fit?: string, actual?: string} $values
     */
    public static function group(string $tag, string $class, string $attribute, array $values): string
    {
        if ($tag !== 'span' && $tag !== 'div') {
            throw new \InvalidArgumentException('Zoom group tag must be span or div.');
        }
        if (!isset($values['smaller'], $values['larger']) || array_diff(array_keys($values), self::ORDER) !== []) {
            throw new \InvalidArgumentException('Zoom group needs smaller and larger, and may add fit and actual.');
        }
        $buttons = '';
        foreach (self::ORDER as $kind) {
            if (isset($values[$kind])) {
                $buttons .= self::button($kind, $attribute, $values[$kind]);
            }
        }
        return '<' . $tag . ' class="zoom-buttons' . ($class !== '' ? ' ' . self::escape($class) : '') . '"'
            . ' role="group" aria-label="Text size">' . $buttons . '</' . $tag . '>';
    }

    private static function attributeName(string $attribute): string
    {
        if (preg_match('/^data-[a-z][a-z0-9-]*$/', $attribute) !== 1) {
            throw new \InvalidArgumentException('Zoom attribute must be a lowercase data-* name.');
        }
        return $attribute;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
