<?php

$theme = [

    /* SEASON: its first and last day in the seasonal schedule (Monday to Sunday of Thanksgiving week) */
    'season' => ['fourth thursday of november -3 days', 'fourth thursday of november +3 days'],

    /* FONT */
    'font_size'        => 4,
    'font_color'       => [239, 226, 212],

    /* SIZE */
    'width'           => 1118,
    'height'          => 290,

    /* BACKGROUND (the cards) */
    'background' => [
        'type' => 'gradient',
        'gradient_top'    => [64, 32, 20],
        'gradient_bottom' => [36, 18, 11],
    ],

    /* GRID & AXIS */
    'grid_color'      => [52, 27, 17],
    'grid_dash_color' => [104, 66, 46],
    'axis_color'      => [150, 110, 86],

    /* MAP BLOCKS */
    'map_block_color' => [64, 32, 20],
    'map_sep_color'   => [150, 110, 86],
    'map_label_color' => [239, 226, 212],

    /* METRIC COLORS (pumpkin, harvest gold, cranberry, sage) */
    'metric_colors' => [
        'max_players' => [242, 184, 75],
        'min_players' => [140, 112, 96],
        'act_players' => [232, 116, 42],
        'uptime'      => [160, 190, 110],
        'fps'         => [214, 69, 58],
        'kills'       => [242, 150, 90],
        'headshots'   => [226, 110, 140],
        'players'     => [214, 69, 58],
        'max_slots'   => [180, 160, 145],
        'act_slots'   => [242, 184, 75],
        'skill'       => [160, 190, 110],
        'deaths'      => [215, 200, 188],
    ],

   /* TREND GRAPH */
    'trend' => [
        'width'  => 520,
        'height' => 320,
    ],

];
