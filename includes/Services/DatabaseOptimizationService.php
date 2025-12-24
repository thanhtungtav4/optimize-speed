<?php

namespace OptimizeSpeed\Services;

use OptimizeSpeed\Core\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseOptimizationService implements ServiceInterface
{
    public function register()
    {
        add_action('wp_ajax_optimize_speed_db_cleanup', [$this, 'handle_cleanup']);
    }

    public function boot()
    {
        // No boot logic needed for now
    }

    public function handle_cleanup()
    {
        check_ajax_referer('optimize_speed_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $type = isset($_POST['cleanup_type']) ? sanitize_text_field($_POST['cleanup_type']) : '';
        $message = '';

        switch ($type) {
            case 'transients':
                $count = $this->clean_transients();
                $message = "Deleted $count expired transients.";
                break;
            case 'all_transients':
                $count = $this->clean_all_transients();
                $message = "Deleted $count transients.";
                break;
            case 'revisions':
                $count = $this->clean_revisions();
                $message = "Deleted $count post revisions.";
                break;
            case 'auto_drafts':
                $count = $this->clean_auto_drafts();
                $message = "Deleted $count auto drafts.";
                break;
            case 'trash_spam':
                $count = $this->clean_trash_spam();
                $message = "Deleted $count spam/trashed items.";
                break;
            case 'optimize_tables':
                $count = $this->optimize_tables();
                $message = "Optimized $count database tables.";
                break;
            default:
                wp_send_json_error(['message' => 'Invalid cleanup type']);
        }

        wp_send_json_success(['message' => $message]);
    }

    private function clean_transients()
    {
        global $wpdb;
        $time = time();

        // Delete expired transients (both timeout and value)
        $sql = $wpdb->prepare(
            "DELETE a, b FROM {$wpdb->options} a
            LEFT JOIN {$wpdb->options} b ON b.option_name = CONCAT('_transient_', SUBSTRING(a.option_name, 20))
            WHERE a.option_name LIKE %s AND a.option_value < %d",
            $wpdb->esc_like('_transient_timeout_') . '%',
            $time
        );

        return $wpdb->query($sql);
    }

    private function clean_all_transients()
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_') . '%',
            $wpdb->esc_like('_site_transient_') . '%'
        );

        return $wpdb->query($sql);
    }

    private function clean_revisions()
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "DELETE a, b, c
            FROM {$wpdb->posts} a
            LEFT JOIN {$wpdb->term_relationships} b ON (a.ID = b.object_id)
            LEFT JOIN {$wpdb->postmeta} c ON (a.ID = c.post_id)
            WHERE a.post_type = %s",
            'revision'
        );

        return $wpdb->query($sql);
    }

    private function clean_auto_drafts()
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "DELETE a, b, c
            FROM {$wpdb->posts} a
            LEFT JOIN {$wpdb->term_relationships} b ON (a.ID = b.object_id)
            LEFT JOIN {$wpdb->postmeta} c ON (a.ID = c.post_id)
            WHERE a.post_status = %s",
            'auto-draft'
        );

        return $wpdb->query($sql);
    }

    private function clean_trash_spam()
    {
        global $wpdb;
        $count = 0;

        // Trash posts
        $sql = $wpdb->prepare(
            "DELETE a, b, c, d
            FROM {$wpdb->posts} a
            LEFT JOIN {$wpdb->term_relationships} b ON (a.ID = b.object_id)
            LEFT JOIN {$wpdb->postmeta} c ON (a.ID = c.post_id)
            LEFT JOIN {$wpdb->comments} d ON (a.ID = d.comment_post_ID)
            WHERE a.post_status = %s",
            'trash'
        );
        $count += $wpdb->query($sql);

        // Spam comments
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->comments} WHERE comment_approved = %s",
            'spam'
        );
        $count += $wpdb->query($sql);

        // Trashed comments
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->comments} WHERE comment_approved = %s",
            'trash'
        );
        $count += $wpdb->query($sql);

        // Clean orphaned postmeta (just in case)
        $sql = "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL";
        $wpdb->query($sql);

        return $count;
    }

    private function optimize_tables()
    {
        global $wpdb;
        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $count = 0;

        foreach ($tables as $table) {
            // Escape table name for security
            $table_name = esc_sql($table[0]);
            $wpdb->query("OPTIMIZE TABLE `{$table_name}`");
            $count++;
        }

        return $count;
    }
}
