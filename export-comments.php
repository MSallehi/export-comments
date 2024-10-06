<?php
/**
 * Plugin Name: خروجی کامنت ها
 * Plugin URI:  https://github.com/MSallehi/export-comments
 * Description: Export comments from selected page or post to CSV.
 * Version:     1.0.0
 * Author:      Mohammad Salehi
 * Author URI:  https://github.com/MSallehi
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: comment-exporter
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('EXPORTCOMMENTDIR', trailingslashit(plugin_dir_path(__FILE__)));
define('EXPORTCOMMENTURL', trailingslashit(plugin_dir_url(__FILE__)));

class Comment_Exporter
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'create_export_page'));

        add_action('admin_post_export_comments_to_csv', array($this, 'export_comments_to_csv'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_action('wp_ajax_search_posts', array($this, 'ajax_search_posts'));
    }

    public function enqueue_scripts($hook)
    {
        if ($hook !== 'comments_page_comment-exporter') {
            return;
        }

        wp_enqueue_style('select2-css', EXPORTCOMMENTURL . 'assets/css/select2.min.css');

        wp_enqueue_script('select2-js', EXPORTCOMMENTURL . 'assets/js/select2.min.js', array('jquery'), null, true);

        wp_enqueue_script('select2-custom', EXPORTCOMMENTURL . 'assets/js/select2-custom.js', array('select2-js'), null, true);
    }

    public function create_export_page()
    {
        add_submenu_page(
            'edit-comments.php',
            'خروجی کامنت',
            'خروجی کامنت',
            'manage_options',
            'comment-exporter',
            array($this, 'export_page_html')
        );
    }

    public function export_page_html()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('خروجی گرفتن از کامنت ها', 'comment-exporter');?></h1>
            <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="export_comments_to_csv">
                <label for="post_id"><?php _e('انتخاب برگه یا نوشته', 'comment-exporter');?></label>
                <select name="post_id" id="post_id" style="width: 50%;">

                </select>
                <?php submit_button(__('خروجی CSV', 'comment-exporter'));?>
            </form>
        </div>
        <?php
}

    public function export_comments_to_csv()
    {
        if (isset($_POST['post_id'])) {
            $post_id = intval($_POST['post_id']);
            $comments = get_comments([
                'post_id' => $post_id,
                'parent' => 0,
                'status' => 'approve',
            ]);

            if (!empty($comments)) {

                $post = get_post($post_id);
                $post_title = sanitize_file_name($post->post_title);

                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment;filename=' . $post_title . '_comments.csv');

                $output = fopen('php://output', 'w');

                fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($output, array('نویسنده سوال', 'نویسنده جواب', 'سوال', 'جواب', 'تاریخ میلادی'));

                foreach ($comments as $comment) {
                    $question_author = isset($comment->comment_author) ? $comment->comment_author : 'بدون نام';
                    $question_content = $comment->comment_content;
                    $date_gregorian = $comment->comment_date;

                    $replies = get_comments([
                        'parent' => $comment->comment_ID,
                        'status' => 'approve',
                    ]);

                    if (!empty($replies)) {
                        foreach ($replies as $reply) {
                            $reply_author = isset($reply->comment_author) ? $reply->comment_author : 'بدون نام';
                            $reply_content = isset($reply->comment_content) ? $reply->comment_content : 'بدون پاسخ';

                            fputcsv($output, array($question_author, $reply_author, $question_content, $reply_content, $date_gregorian));
                        }
                    } else {
                        fputcsv($output, array($question_author, $question_content, 'بدون پاسخ', 'بدون پاسخ', $date_gregorian));
                    }
                }

                fclose($output);
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=comment-exporter&error=no_comments'));
                exit;
            }
        }
    }
    public function ajax_search_posts()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            's' => $search_term,
            'posts_per_page' => 10,
        ));

        $results = array();

        foreach ($posts as $post) {
            $results[] = array(
                'id' => $post->ID,
                'text' => $post->post_title . ' (' . ucfirst($post->post_type) . ')',
            );
        }

        wp_send_json($results);
    }
}

new Comment_Exporter();