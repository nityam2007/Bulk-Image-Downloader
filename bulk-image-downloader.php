<?php
/*
Plugin Name: Bulk Image Downloader
Plugin URI: https://wordpress.org/plugins/bulk-image-downloader
Description: Allows bulk downloading of images from external URLs and saves them in the WordPress media library.
Version: 1.0.0
Author: Nityamas
Author URI: https://wordpress.org/support/users/nityamas/
License: GPLv2 or later
Text Domain: bulk-image-downloader
*/

// Register a custom admin menu page
function bulk_image_downloader_menu_page() {
    add_media_page(
        'Bulk Image Downloader',
        'Bulk Image Downloader',
        'upload_files',
        'bulk-image-downloader',
        'bulk_image_downloader_render_menu_page',
        'dashicons-download',
        25
    );
}
add_action('admin_menu', 'bulk_image_downloader_menu_page');

// Render the custom admin menu page
function bulk_image_downloader_render_menu_page() {
    if (!current_user_can('upload_files')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p><?php echo esc_html__('This plugin allows you to bulk download images from external URLs and save them in the WordPress media library. To use the plugin, follow these steps:', 'bulk-image-downloader'); ?></p>
        <ol>
            <li><?php echo esc_html__('Enter the image URLs in the textarea provided, with each URL on a new line.', 'bulk-image-downloader'); ?></li>
            <li><?php echo esc_html__('Click the "Download Images" button to initiate the download process.', 'bulk-image-downloader'); ?></li>
            <li><?php echo esc_html__('The downloaded images will be added to the media library, and you will see a list of the downloaded images.', 'bulk-image-downloader'); ?></li>
            <li><?php echo esc_html__('Click the "View Media Library" button to go to the media library and manage the downloaded images.', 'bulk-image-downloader'); ?></li>
        </ol>
        <p><?php echo esc_html__('Note: Make sure the URLs are valid and accessible. Also, be cautious when downloading images from external sources and ensure that you have the necessary permissions to use them.', 'bulk-image-downloader'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="bulk_image_downloader_process">
            <?php wp_nonce_field('bulk_image_downloader_process', 'bulk_image_downloader_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Image URLs:', 'bulk-image-downloader'); ?></th>
                    <td>
                        <textarea name="image_urls" rows="10" style="width: 100%;"></textarea>
                        <p class="description"><?php echo esc_html__('Enter the image URLs, one per line.', 'bulk-image-downloader'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__('Download Images', 'bulk-image-downloader'); ?>">
                <a class="button" href="<?php echo esc_url(admin_url('upload.php')); ?>"><?php echo esc_html__('View Media Library', 'bulk-image-downloader'); ?></a>
            </p>
        </form>
        <p><?php echo esc_html__('By Nityamas', 'bulk-image-downloader'); ?></p>
    </div>
    <?php
}

// Process the bulk image download request
function bulk_image_downloader_process_request() {
    if (!isset($_POST['bulk_image_downloader_nonce']) || !wp_verify_nonce($_POST['bulk_image_downloader_nonce'], 'bulk_image_downloader_process')) {
        wp_die('Invalid nonce');
    }

    if (!current_user_can('upload_files')) {
        wp_die('You are not allowed to upload files');
    }

    $image_urls = isset($_POST['image_urls']) ? sanitize_textarea_field($_POST['image_urls']) : '';

    if (!empty($image_urls)) {
        $image_urls = explode("\n", $image_urls);
        $image_urls = array_map('trim', $image_urls);
        $image_urls = array_filter($image_urls);

        $uploaded_images = array();

        foreach ($image_urls as $image_url) {
            $image_url = esc_url_raw($image_url);

            // Remove query string from the image URL
            $image_url = preg_replace('/\?.*/', '', $image_url);

            $response = wp_remote_get($image_url);

            if (is_array($response) && !empty($response['headers']['content-type'])) {
                $content_type = $response['headers']['content-type'];
                $extension = '';

                if ($content_type === 'image/jpeg') {
                    $extension = 'jpg';
                } elseif ($content_type === 'image/png') {
                    $extension = 'png';
                } elseif ($content_type === 'image/gif') {
                    $extension = 'gif';
                }

                if ($extension) {
                    $file_data = $response['body'];
                    $file_name = basename($image_url);
                    $file_path = wp_upload_dir()['path'] . '/' . $file_name;

                    if (wp_mkdir_p(wp_upload_dir()['path'])) {
                        file_put_contents($file_path, $file_data);

                        $attachment = array(
                            'guid' => wp_upload_dir()['url'] . '/' . $file_name,
                            'post_mime_type' => $content_type,
                            'post_title' => preg_replace('/\.[^.]+$/', '', $file_name),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        );

                        $attachment_id = wp_insert_attachment($attachment, $file_path);
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
                        wp_update_attachment_metadata($attachment_id, $attachment_data);

                        $uploaded_images[] = $attachment_id;
                    }
                }
            }
        }

        if (!empty($uploaded_images)) {
            echo '<div class="updated"><p>' . esc_html__('Images downloaded and added to the media library:', 'bulk-image-downloader') . '</p>';
            foreach ($uploaded_images as $attachment_id) {
                echo '<p><a href="' . esc_url(get_edit_post_link($attachment_id)) . '">' . esc_html(get_the_title($attachment_id)) . '</a></p>';
            }
            echo '</div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('No images were downloaded.', 'bulk-image-downloader') . '</p></div>';
        }

        echo '<p><a class="button" href="' . esc_url(admin_url('upload.php')) . '">' . esc_html__('Go to Media Library', 'bulk-image-downloader') . '</a></p>';
    }
}
add_action('admin_post_bulk_image_downloader_process', 'bulk_image_downloader_process_request');
