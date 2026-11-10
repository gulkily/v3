<?php

declare(strict_types=1);

namespace ForumRewrite\View;

/**
 * The site's Smaller/Larger text-size buttons: magnifier icons, labels and the shared
 * .zoom-buttons group (site.css). Used by the Query viewer, the Forte toolbar and tables
 * in Markdown posts, so all three look and read the same. Each place keeps its own data
 * attribute and script; the stepping code is shared separately (ZenMemes thread
 * thread-20261110231618-168b822f).
 */
final class ZoomButtons
{
    private const ICON_SMALLER = '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">'
        . '<circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor"/>'
        . '<line x1="10.2" y1="10.2" x2="14" y2="14" stroke="currentColor"/>'
        . '<line x1="5" y1="7" x2="9" y2="7" stroke="currentColor"/></svg>';

    private const ICON_LARGER = '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">'
        . '<circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor"/>'
        . '<line x1="10.2" y1="10.2" x2="14" y2="14" stroke="currentColor"/>'
        . '<line x1="5" y1="7" x2="9" y2="7" stroke="currentColor"/>'
        . '<line x1="7" y1="5" x2="7" y2="9" stroke="currentColor"/></svg>';

    /**
     * One Smaller or Larger button. $attribute is the data attribute the page's script
     * listens for (e.g. "data-query-zoom"); $class is any extra class for the button.
     */
    public static function button(string $direction, string $attribute, string $value, string $class = ''): string
    {
        if ($direction !== 'smaller' && $direction !== 'larger') {
            throw new \InvalidArgumentException('Zoom direction must be "smaller" or "larger".');
        }
        $label = $direction === 'smaller' ? 'Smaller' : 'Larger';
        return '<button type="button"' . ($class !== '' ? ' class="' . self::escape($class) . '"' : '')
            . ' ' . self::attributeName($attribute) . '="' . self::escape($value) . '"'
            . ' aria-label="' . $label . '" title="' . $label . '">'
            . ($direction === 'smaller' ? self::ICON_SMALLER : self::ICON_LARGER) . '</button>';
    }

    /**
     * A labelled group: Smaller, an optional Fit button, then Larger. $values maps
     * "smaller", "larger" and optionally "fit" to the data attribute's values.
     *
     * @param array{smaller: string, larger: string, fit?: string} $values
     */
    public static function group(string $tag, string $class, string $attribute, array $values): string
    {
        if ($tag !== 'span' && $tag !== 'div') {
            throw new \InvalidArgumentException('Zoom group tag must be span or div.');
        }
        $name = self::attributeName($attribute);
        $fit = isset($values['fit'])
            ? '<button type="button" ' . $name . '="' . self::escape($values['fit']) . '" title="Fit to screen">Fit</button>'
            : '';
        return '<' . $tag . ' class="zoom-buttons' . ($class !== '' ? ' ' . self::escape($class) : '') . '"'
            . ' role="group" aria-label="Text size">'
            . self::button('smaller', $attribute, $values['smaller'])
            . $fit
            . self::button('larger', $attribute, $values['larger'])
            . '</' . $tag . '>';
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
