<?php

declare(strict_types=1);

namespace Pushery\VisualFeedback\Support;

/**
 * The screenshot editor's texts in the current locale, keyed the way the editor reads them.
 *
 * The editor's bundle builds its controls in the browser and carries no word of its own, so both
 * view trees hand these over as JSON in the dialog's `data-visual-feedback-editor` attribute. The
 * texts themselves are translations under `visual-feedback::messages.widget`, in every locale the
 * package ships.
 */
final class EditorTexts
{
    /** The editor's key, and the translation key it reads. */
    private const array KEYS = [
        'image' => 'editor_image',
        'toolbar' => 'editor_toolbar',
        'undo' => 'editor_undo',
        'redo' => 'editor_redo',
        'reset' => 'editor_reset',
        'apply' => 'editor_apply',
        'cancel' => 'editor_cancel',
        'region' => 'editor_region',
        'regionHelp' => 'editor_region_help',
        'regionValue' => 'editor_region_value',
        'fieldX' => 'editor_field_x',
        'fieldY' => 'editor_field_y',
        'fieldW' => 'editor_field_w',
        'fieldH' => 'editor_field_h',
        'toolGroup' => 'editor_tool_group',
        'toolCrop' => 'editor_tool_crop',
        'toolArrow' => 'editor_tool_arrow',
        'toolLine' => 'editor_tool_line',
        'toolEllipse' => 'editor_tool_ellipse',
        'toolRect' => 'editor_tool_rect',
        'toolFreehand' => 'editor_tool_freehand',
        'colorGroup' => 'editor_color_group',
        'colorRed' => 'editor_color_red',
        'colorOrange' => 'editor_color_orange',
        'colorGreen' => 'editor_color_green',
        'colorBlue' => 'editor_color_blue',
        'colorBlack' => 'editor_color_black',
        'widthGroup' => 'editor_width_group',
        'widthThin' => 'editor_width_thin',
        'widthMedium' => 'editor_width_medium',
        'widthThick' => 'editor_width_thick',
        'remove' => 'editor_remove',
        'markHelp' => 'editor_mark_help',
        'markLabel' => 'editor_mark_label',
        'markAdded' => 'editor_mark_added',
        'markDeleted' => 'editor_mark_deleted',
        'markEndStart' => 'editor_mark_end_start',
        'markEndEnd' => 'editor_mark_end_end',
        'shapeArrow' => 'editor_shape_arrow',
        'shapeLine' => 'editor_shape_line',
        'shapeEllipse' => 'editor_shape_ellipse',
        'shapeRect' => 'editor_shape_rect',
        'shapeFreehand' => 'editor_shape_freehand',
    ];

    /**
     * Every text, translated. A text with placeholders keeps them, `:x`, `:y`, `:w`, `:h` and
     * `:mark`, and the editor fills them with percentages and the name of a mark.
     *
     * @return array<string, string>
     */
    public static function current(): array
    {
        return array_map(
            static fn (string $key): string => (string) trans('visual-feedback::messages.widget.'.$key),
            self::KEYS,
        );
    }
}
