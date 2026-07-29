<?php
// wizard_nav.php

// --- DEBUGGING: Force display of errors. Remove these lines in production. ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure the config is available. This path is correct when both files are in the root.
require_once __DIR__ . '/wizard_config.php';

/**
 * Renders the main top-level stepper navigation for the wizard.
 *
 * @param string $current_main_step_key The key of the active main step (e.g., 'tokenomics').
 */
function render_main_stepper($current_main_step_key) {
    $config = get_wizard_config();
    $main_steps = array_keys($config);
    $current_main_index = array_search($current_main_step_key, $main_steps);

    // MODIFIED: Reduced bottom margin from mb-10 to mb-8
    echo '<div class="w-full max-w-4xl mx-auto mb-8 px-4 md:px-0">';
    echo '<div class="flex items-start justify-between">';

    foreach ($main_steps as $index => $key) {
        $step_data = $config[$key];
        $is_active = ($index === $current_main_index);
        $is_completed = ($index < $current_main_index);

        $icon_class = 'bg-white border-2 border-slate-300 text-slate-400';
        $text_class = 'text-slate-500';
        // MODIFIED: Reduced font size for a better fit in the smaller circle
        $step_indicator = '<span class="font-bold text-sm">' . ($index + 1) . '</span>';

        if ($is_active) {
            $icon_class = 'bg-purple-50 border-2 border-purple-600 text-purple-600';
            $text_class = 'text-purple-600 font-semibold';
        } elseif ($is_completed) {
            $icon_class = 'bg-purple-50 border-2 border-purple-600 text-purple-600';
            $text_class = 'text-purple-600 font-medium';
        }
        
        echo '<div class="flex items-start ' . ($index < count($main_steps) - 1 ? 'flex-1' : '') . '">';
        // MODIFIED: Increased width from w-24 to w-32 to accommodate longer titles like "Private Sale Room"
        echo '<div class="flex flex-col items-center text-center w-32">';
        // MODIFIED: Reduced circle size from w-10 h-10 to w-8 h-8
        echo '<div class="w-8 h-8 rounded-full flex items-center justify-center ' . $icon_class . ' transition-all">' . $step_indicator . '</div>';
        echo '<p class="mt-2 text-xs leading-tight ' . $text_class . '">' . htmlspecialchars($step_data['mainStep']) . '</p>';
        echo '</div>';

        if ($index < count($main_steps) - 1) {
            // MODIFIED: Adjusted connector margin-top from mt-5 to mt-4 to match smaller circle
            $connector_class = $is_completed ? 'bg-purple-600' : 'bg-slate-200';
            echo '<div class="flex-1 h-0.5 mt-4 ' . $connector_class . '"></div>';
        }
        echo '</div>';
    }

    echo '</div></div>';
}

/**
 * Renders the sub-step progress bar for a given main step.
 *
 * @param string $current_main_step_key The key of the parent main step (e.g., 'tokenomics').
 * @param string $current_sub_step_key The key of the active sub-step (e.g., 'token_name').
 */
function render_sub_stepper($current_main_step_key, $current_sub_step_key) {
    $config = get_wizard_config();
    if (!isset($config[$current_main_step_key]['subSteps'])) {
        return; // No sub-steps for this section
    }

    $sub_steps = $config[$current_main_step_key]['subSteps'];
    $sub_step_keys = array_keys($sub_steps);
    $current_sub_index = array_search($current_sub_step_key, $sub_step_keys);
    
    if ($current_sub_index !== false) {
        $current_step_num = $current_sub_index + 1;
        $total_steps = count($sub_step_keys);
        $percentage = ($current_step_num / $total_steps) * 100;

        echo '<div class="mb-8">';
        echo '<div class="mb-2 text-sm font-semibold text-slate-700">';
        echo 'Step ' . $current_step_num . ' / ' . $total_steps . ' of ' . htmlspecialchars($config[$current_main_step_key]['mainStep']);
        echo '</div>';
        echo '<div class="bg-slate-200 rounded-full h-2 w-full">';
        echo '<div class="bg-gradient-to-r from-purple-600 to-cyan-500 h-2 rounded-full transition-all" style="width: ' . $percentage . '%;"></div>';
        echo '</div>';
        echo '</div>';
    }
}