<?php
/*
Plugin Name: Topcontent
Plugin URI: https://wordpress.org/plugins/topcontent/
Description: At Topcontent we work hard to produce the text you need for your websites. With the Topcontent plugin, you can place translations orders and have content orders automatically published directly to your website.
Version: 1.2.1
Author: Topcontent
Author URI: https://topcontent.com/
*/

/*  Copyright 2020 Topcontent (email: support@topcontent.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Stop direct call
if (preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) die('Not allowed to call this page directly.');

define('TopContent', true);

if (is_admin()) {
    add_action('admin_enqueue_scripts', 'topcont_admin_scripts');
    add_action('admin_enqueue_scripts', 'topcont_admin_styles');
    add_action('admin_menu', 'topcont_admin_menu');
} else {
    add_action('init', 'topcont_callback');
}

add_filter('plugin_action_links', 'topcont_add_settings_link', 10, 2);
add_filter('network_admin_plugin_action_links', 'topcont_add_settings_link', 10, 2);

require_once(plugin_dir_path(__FILE__) . 'assets/libs/class.linking.rules.php');

function topcont_add_settings_link($links, $file)
{
    if ($file === basename(__DIR__) . '/' . basename(__FILE__) && current_user_can('manage_options')) {
        if (current_filter() === 'plugin_action_links') {
            $url = admin_url('admin.php?page=topcont-setting');
        }
        array_unshift($links, sprintf('<a href="%s">%s</a>', $url, __('General', 'topcontent')));
    }
    return $links;
}

function topcont_admin_scripts()
{
    wp_enqueue_script('admin-topcontent', plugins_url('assets/js/admin-topcontent.js?v=1.2', __FILE__), array('jquery', 'jquery-ui-tabs'));
}

function topcont_admin_styles()
{
    wp_enqueue_style('jquery-ui-tabs', plugins_url('assets/css/jquery-ui.min.css', __FILE__));
    wp_enqueue_style('topcontent', plugins_url('assets/css/admin-topcontent.css?v=1.2', __FILE__));
}

function topcont_admin_menu()
{
    add_menu_page('Topcontent WordPress Plugin', 'Topcontent', 'manage_options', 'topcont-setting', 'topcont_admin_menu_html', 'dashicons-editor-paste-text', '80.08');
}

function topcontent_get_author($author)
{
    if ($author !== 'random') {
        return $author;
    }

    global $wpdb;
    $ids = $wpdb->get_results("SELECT id FROM $wpdb->users ORDER BY RAND() LIMIT 1");

    return (int)($ids[0]->id);
}

function topcontent_get_title_and_body($item)
{
    $title = $item->h1;
    $body = $item->content->body;

    // Attempt to take first h1
    if (get_option('topcont-first-h1', '')) {
        if (preg_match('/<h1>(.*?)<\/h1>/i', $item->content->body, $matches) && count($matches) > 1 && $matches[1]) {
            $title = $matches[1];
            $search = '/' . preg_quote('<h1>' . $title . '</h1>', '/') . '/';
            $body = preg_replace($search, '', $body, 1);
        }
    }

    return ['title' => $title, 'body' => $body];
}

function topcontent_clean_title($title)
{
    $title = str_ireplace('‒', '-', $title);
    return sanitize_title($title);
}

function topcont_admin_menu_html()
{
    load_template(plugin_dir_path(__FILE__) . 'assets/libs/class.topcontent.php');
    $topcont = new TOPCONT_API;
    $balance = $topcont->getBalance();
    $payment = $topcont->getPaymentLink(home_url() . '/wp-admin/admin.php?' . http_build_query(map_deep($_GET, 'sanitize_text_field')));

    $users = array_merge(
        get_users(array(
            'role__in' => 'administrator',
            'fields' => ['ID', 'display_name', 'user_email'],
        )),
        get_users(array(
            'role__not_in' => 'administrator',
            'fields' => ['ID', 'display_name', 'user_email'],
        ))
    );
    ?>

    <div class="wrap">
        <h2 class="topcont-hide"><?= get_admin_page_title() ?></h2>
        <div class="topcont">
            <div id="topcont-tabs">
                <ul>
                    <li><img class="topcont-logo" src="<?= plugin_dir_url(__FILE__) ?>/assets/images/topcont_logo.png"></li>
                    <li><a href="#topcont-tabs-1">General</a></li>
                    <li><a href="#topcont-tabs-2">Translations settings</a></li>
                    <li><a href="#topcont-tabs-3">Content settings</a></li>
                </ul>
                <div id="topcont-tabs-1">

                    <h3>API Key</h3>
                    <p>Login to the Topcontent self-service to <a href="https://app.topcontent.com/settings/api" target="_blank">access your API Key</a>. A Topcontent is free so just <a href="https://app.topcontent.com/signup" target="_blank">sign up</a> if you haven’t already</p>

                    <p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate="novalidate" <?= $balance->status_code == 200 ? ' class="topcont-hide"' : '' ?>>
                        <input type="hidden" name="form_id" value="topcont-api-key-form" />
                        <input type="hidden" name="action" value="topcont" />
                        <input type="hidden" name="topcont_data_fields_nonce" value="<?php echo wp_create_nonce(__FILE__); ?>" />
                        <input type="hidden" name="redirect" value="<?= '/wp-admin/admin.php?' . http_build_query(map_deep($_GET, 'esc_html')) ?>" />
                        <label><b>API Key</b> <input type="text" name="topcont-api-key" class="regular-text" placeholder="d9Z6AnDTrc7XuugVDAlHFHnBEt" value="<?= get_option('topcont-api-key', '') ?>" /></label> <button class="topcont-api-key-save">Save API Key</button> <?php if (!empty(get_option('topcont-api-key', '')) && $balance->status_code != 401) : ?><a href="" class="topcont-api-key-cancel">Cancel</a><?php endif; ?>
                    </form>
                    <?php if ($balance->status_code == 200) : ?>
                        <div class="topcont-msg topcont-msg-ok">Your Topcontent account has been successfully linked to this WordPress site. <a href="" class="topcont-api-key-change">Change API key</a></div>
                    <?php endif; ?>
                    <?php if (get_option('_topcont_key_error') == 1) : ?>
                        <div class="topcont-msg topcont-msg-error">Error linking account. Invalid API Key.</div>
                        <?php update_option('_topcont_key_error', 0); ?>
                    <?php endif; ?>
                    <?php if ($balance->status_code == 200) : ?>
                        <h4>Balance at Topcontent: € <?= number_format($balance->credits, 2, '.', '') ?> <a href="<?= $payment->href ?>" target="_blank">Top up</a></h4>
                    <?php endif; ?>

                    </p>
                    <?php if (isset($_GET['debug_mode']) && $_GET['debug_mode'] == 1) : ?>
                        <p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate="novalidate">
                            <input type="hidden" name="form_id" value="topcont-api-url-form" />
                            <input type="hidden" name="action" value="topcont" />
                            <input type="hidden" name="topcont_data_fields_nonce" value="<?php echo wp_create_nonce(__FILE__); ?>" />
                            <input type="hidden" name="redirect" value="<?= '/wp-admin/admin.php?' . http_build_query(map_deep($_GET, 'esc_html')) ?>" />
                            <label><b>API URL</b> <input type="text" name="topcont-api-url" class="regular-text" placeholder="https://api.topcontent.com/v2/" value="<?= get_option('topcont-api-url', 'https://api.topcontent.com/v2/') ?>" /> <button class="topcont-api-url-save">Save API URL</button>
                        </form>
                        </p>

                    <?php endif; ?>
                    <?php if ($balance->status_code == 404) : ?>
                        <div class="topcont-msg topcont-msg-error">Invalid API URL.</div>
                    <?php endif; ?>

                    <h3>Content</h3>
                    <p>Place content orders using our <a href="https://app.topcontent.com/" target="_blank">self-service</a> and get finalised pages and posts sent directly to this WordPress website.</p>
                    <p><b>When ordering, you can choose:</b></p>
                    <li>Language and quality level.</li>
                    <li>If it’s a Page or Post.</li>
                    <li>If it should be published immediately, scheduled for later or saved as a draft.</li>
                    <li>Publish date (perfect for when it should be scheduled).</li>

                    <h3>Translations</h3>
                    <p>You can order translations directly from this WordPress plugin.</p>
                    <p><b>How to set it up:</b></p>
                    <li>Install the <a href="https://wordpress.org/plugins/polylang/" target="_blank">PolyLang WordPress plugin</a>.</li>
                    <li>Choose which languages you want in the PolyLang plugin.</li>
                    <li>Set which Custom Fields should be translated in the <a href="#topcont-tabs-2" class="topcont-translation">Translation Settings</a>.</li>
                    <li>Top up your Topcontent account.</li>

                    <p><b>How to order:</b></p>
                    <li>Go to your pages or post page in the WordPress Dashboard.</li>
                    <li>You will see which ones are translated or not.</li>
                    <li>Select one and click to send for translations.</li>

                    <!-- <p>[Insert image here showing what it looks like]</p> -->

                    <p>The content will be sent to Topcontent for translations and will be automatically published in the correct language when done.</p>

                </div>
                <div id="topcont-tabs-2">

                    <h3>You caught us before we're ready.</h3>
                    <p>We're working hard to put this finishing touches onto Topcontent. Things are going well and it should be ready to help you with translations very soon.</p>

                </div>
                <div id="topcont-tabs-3">

                    <h3>General settings</h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="form_id" value="topcont-api-change-form" />
                        <input type="hidden" name="action" value="topcont" />
                        <input type="hidden" name="topcont_data_fields_nonce" value="<?php echo wp_create_nonce(__FILE__); ?>" />
                        <input type="hidden" name="redirect" value="<?= '/wp-admin/admin.php?' . http_build_query(map_deep($_GET, 'esc_html')) . '#topcont-tabs-3' ?>" />
                        <p><label>Always save new posts and pages as "Draft": <input type="hidden" name="topcont-draft" value=""><input type="checkbox" name="topcont-draft" value="1" <?= checked(get_option('topcont-draft', '')) ?> /></label></p>
                        <p><label>Use first image as Featured Image: <input type="hidden" name="topcont-featured-image" value=""><input type="checkbox" name="topcont-featured-image" value="1" <?= checked(get_option('topcont-featured-image', '')) ?> /></label></p>
                        <p><label>Use the top H1, in received content, as WordPress Title: <input type="hidden" name="topcont-first-h1" value=""><input type="checkbox" name="topcont-first-h1" value="1" <?= checked(get_option('topcont-first-h1', '')) ?> /></label></p>
                        <p>
                            <label>Author of posts and pages from Topcontent:
                                <select name="topcont-author">
                                    <optgroup label="Users">
                                        <?php foreach ($users as $user) : ?>
                                            <option value="<?= $user->ID ?>" <?= selected($user->ID, get_option('topcont-author', '')) ?>><?= $user->display_name ?> (<?= $user->user_email ?>)</option>
                                        <?php endforeach; ?>
                                        <optgroup>

                                            <optgroup label="Other">
                                                <option value="random" <?= selected('random', get_option('topcont-author', '')) ?>>Random user</option>
                                            </optgroup>
                                </select>
                            </label>
                        </p>

                        <h4>
                            Rules for Internal Linking
                            <button id="topcont-add-rule-button" class="topcont-button">Add New</button>
                        </h4>
                        <table id="topcont-linking-rules-row" class="topcont-table topcont-hide">
                            <tr>
                                <td style="width: 30%">
                                    <?= LinkingRules::buildWpCategoriesList() ?>
                                </td>
                                <td style="width: 30%">
                                    <textarea class="topcont-w-100 topcont-h-100px" rows="4" placeholder="List of Anchor Phrases..."></textarea>
                                </td>
                                <td style="width: 20%">
                                    <input type="url" class="topcont-w-100" placeholder="Target URL..." />
                                </td>
                                <td class="topcont-centered" style="width: 20%">
                                    <button class="topcont-button topcont-delete-button">Delete Rule</button>
                                </td>
                            </tr>
                        </table>

                        <div id="topcont-linking-rules">
                            <?php $style = (!LinkingRules::hasRules()) ? 'block' : 'none'; ?>
                            <p id="topcont-no-rules" class="topcont-centered" style="display: <?= $style ?>;">
                                No rules yet.
                            </p>

                            <?php if (LinkingRules::hasRules()) { ?>
                                <?php
                                $categories = LinkingRules::getRules(LinkingRules::RULES_CATEGORIES);
                                $phrases = LinkingRules::getRules(LinkingRules::RULES_PHRASES);
                                $urls = LinkingRules::getRules(LinkingRules::RULES_URLS);
                                ?>
                                <?php foreach ($categories as $k => $val) { ?>
                                    <table class="topcont-table">
                                        <tr>
                                            <td style="width: 30%">
                                                <?= LinkingRules::buildWpCategoriesList(array(
                                                    'selected' => $categories[$k],
                                                    'is_required' => true,
                                                    'dropdown_name' => 'topcont-rules-categories[' . $k . '][]'
                                                )) ?>
                                            </td>
                                            <td style="width: 30%">
                                                <textarea name="topcont-rules-phrases[<?= $k ?>][]" class="topcont-w-100 topcont-h-100px" rows="4" placeholder="List of Anchor Phrases..." required><?= $phrases[$k][0] ?></textarea>
                                            </td>
                                            <td style="width: 20%">
                                                <input name="topcont-rules-urls[<?= $k ?>][]" type="url" class="topcont-w-100" value="<?= $urls[$k][0] ?>" placeholder="Target URL..." required />
                                            </td>
                                            <td class="topcont-centered" style="width: 20%">
                                                <button class="topcont-button topcont-delete-button">Delete Rule</button>
                                            </td>
                                        </tr>
                                    </table>
                                <?php } ?>
                            <?php } ?>
                        </div>

                        <p><button class="topcont-button" id="topcont-save-changes">Save Changes</button></p>
                    </form>

                </div>
            </div>
        </div>
    </div>
    <?php
}

add_action('admin_post_topcont', 'topcont_post_save');
add_action('admin_post_nopriv_topcont', 'topcont_post_save');

function topcont_post_save()
{
    if (!wp_verify_nonce($_POST['topcont_data_fields_nonce'], __FILE__)) return false;
    switch ($_POST['form_id']) {
        case 'topcont-api-key-form':
            $key = get_option('topcont-api-key');
            if ($key != trim($_POST['topcont-api-key'])) {
                update_option('topcont-api-key', trim(sanitize_text_field($_POST['topcont-api-key'])));
                load_template(plugin_dir_path(__FILE__) . 'assets/libs/class.topcontent.php');
                $topcont = new TOPCONT_API;
                $balance = $topcont->getBalance();
                if ($balance->status_code != 200) {
                    update_option('topcont-api-key', $key);
                    update_option('_topcont_key_error', 1);
                }
            }
            break;
        case 'topcont-api-url-form':
            if (get_option('topcont-api-url') != trim($_POST['topcont-api-url'])) {
                update_option('topcont-api-url', trim(sanitize_text_field($_POST['topcont-api-url'])));
            }
            break;
        case 'topcont-api-change-form':
            if (get_option('topcont-draft') != $_POST['topcont-draft']) {
                update_option('topcont-draft', sanitize_text_field($_POST['topcont-draft']));
            }
            if (get_option('topcont-featured-image') != $_POST['topcont-featured-image']) {
                update_option('topcont-featured-image', sanitize_text_field($_POST['topcont-featured-image']));
            }
            if (get_option('topcont-first-h1') != $_POST['topcont-first-h1']) {
                update_option('topcont-first-h1', sanitize_text_field($_POST['topcont-first-h1']));
            }
            if (get_option('topcont-author') != $_POST['topcont-author']) {
                update_option('topcont-author', sanitize_text_field($_POST['topcont-author']));
            }

            LinkingRules::updateRules();

            break;
    }
    wp_redirect($_POST['redirect']);
    exit();
}

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(plugin_dir_path(__FILE__) . 'assets/libs/сlass.custom.html.handler.php');

function topcont_callback()
{

    // Getting webhook's data
    $data = json_decode(file_get_contents('php://input'));

    // Verify api_key
    if (isset($data->api_key) && get_option('topcont-api-key', false) == $data->api_key) {

        if (($data->event == 'approved' || $data->event == 'auto_approved')) {
            $responseData = [];

            $posts = get_posts(array(
                'numberposts' => -1,
                'post_type' => 'any',
                'post_status' => 'any',
                'meta_key'    => '_topcont_id',
                'meta_value'  => $data->item->item_id,
            ));

            $custom_data = json_decode($data->item->custom_data);
            $result = topcontent_get_title_and_body($data->item);

            if (!empty($custom_data->custom_html)) {
                $customHtml = new TOPCONTENT_CUSTOM_HTML();
                $result = $customHtml->handle($result, $custom_data->custom_html);
            }

            if (!empty($posts)) {
                $post_id = $posts[0]->ID;
                kses_remove_filters();
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $result['title'],
                    'post_content' => $result['body'],
                    'post_name' => topcontent_clean_title($result['title']),
                ));
                kses_init_filters();
            } else {
                $post_status = $custom_data->publish_status == 'published' && get_option('topcont-draft', '') == false
                    ? 'publish'
                    : 'draft';

                if (strtotime($custom_data->publish_date) > time() && $post_status == 'publish') {
                    $post_status = 'future';
                }

                if (!isset($custom_data->publish_date) || empty($custom_data->publish_date)) {
                    $custom_data->publish_date = date('Y-m-d H:i:s', time());
                }

                $post_data = array(
                    'post_type'     => $custom_data->type,
                    'post_title'    => $result['title'],
                    'post_name'     => topcontent_clean_title($result['title']),
                    'post_content'  => $result['body'],
                    'post_status'   => $post_status,
                    'post_author'   => topcontent_get_author(get_option('topcont-author', 1)),
                    'post_date_gmt' => date('Y-m-d H:i:s', strtotime($custom_data->publish_date)),
                );

                if (isset($custom_data->slug) && !empty($custom_data->slug)) {
                    $post_data['post_name'] = topcontent_clean_title($custom_data->slug);
                }

                if (!empty($custom_data->post_slug)) {
                    $post_data['post_name'] = topcontent_clean_title($custom_data->post_slug);
                }

                if ($custom_data->type == 'post') {
                    require_once ABSPATH . './wp-admin/includes/taxonomy.php';

                    // Detect categories
                    $categories = array();

                    foreach ($custom_data->categories as $cat_name) {
                        $cat_id = get_cat_ID($cat_name);

                        if ($cat_id != 0) {
                            $categories[] = $cat_id;
                        } else {
                            $cat_id = wp_create_category($cat_name);
                            if ($cat_id != 0) $categories[] = $cat_id;
                        }
                    }

                    $post_data['post_category'] = $categories;

                    // Apply Linking Rules
//                    $post_data['post_content'] = LinkingRules::applyRules($post_data['post_content'], $categories);
                }

                kses_remove_filters();
                $post_id = wp_insert_post($post_data);
                kses_init_filters();

                update_post_meta($post_id, '_topcont_id', $data->item->item_id);

                if (!empty($custom_data->featured_image)) {
                    $attachment = media_sideload_image($custom_data->featured_image, $post_id, null, 'id');
                    set_post_thumbnail($post_id, $attachment);
                }

                if (!empty($custom_data->tags) && is_array($custom_data->tags)) {
                    wp_set_post_tags($post_id, $custom_data->tags);
                }
            }

            // Upload images
            preg_match_all('/img\s.*?src="([^"]+)"/', $result['body'], $out);

            if (!empty($out[1])) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';

                $images = array_unique($out[1]);

                $post_content = $result['body'];

                $first = true;

                foreach ($images as $image) {
                    if ($attachment = media_sideload_image($image, $post_id, null, 'id')) {
                        $src = wp_get_attachment_url($attachment);
                        $post_content = str_replace($image, $src, $post_content);

                        if ($first) {
                            $first = false;
                            // Extract the featured image from content
                            if (get_option('topcont-featured-image', '') && empty($custom_data->featured_image)) {
                                set_post_thumbnail($post_id, $attachment);
                            }
                        }
                    } else {
                        $post_content = str_replace($image, '', $post_content);
                    }
                }

                wp_update_post(array('ID' => $post_id, 'post_content' => $post_content));
            }

            // Yoast SEO params
            if (defined('WPSEO_FILE')) {
                global $wpdb;

                update_post_meta($post_id, '_yoast_wpseo_focuskw', $data->item->focus_keyword[0]->keyword);
                update_post_meta($post_id, '_yoast_wpseo_title', $data->item->content->meta_title);
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $data->item->content->meta_description);

                $wpdb->update($wpdb->prefix . "yoast_indexable", array('primary_focus_keyword' => $data->item->focus_keyword[0]->keyword, 'title' => $data->item->content->meta_title, 'description' => $data->item->content->meta_description), array('object_id' => $post_id));
            }

            $responseData['post_id'] = $post_id;

            echo json_encode($responseData, JSON_UNESCAPED_UNICODE);
            exit();
        }


        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Verify api_key
    if (isset($_POST['requestor']) && $_POST['requestor'] == 'topcontent') {

        $result = array();

        $result['topcontent_plugin'] = true;

        $result['api_key'] = get_option('topcont-api-key') !== false ? true : false;

        if (isset($_POST['api_key']) && get_option('topcont-api-key', false) == $_POST['api_key']) {

            if (isset($_POST['url'])) {

                $post_id = url_to_postid(esc_url_raw($_POST['url']));

                if ($post_id != 0) {
                    $post = get_post($post_id);
                    $result['post_type'] = $post->post_type;
                    $result['post_title'] = $post->post_title;
                    $result['post_content'] = $post->post_content;
                } else {
                    $result['errors'] = array(
                        'error_message' => 'Article is not found.'
                    );
                }
            } else {

                $result['errors'] = array(
                    'error_message' => 'url parameter is missing.'
                );
            }
        } elseif (isset($_POST['api_key'])) {

            $result['errors'] = array(
                'error_message' => 'API Key not conform.'
            );
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit();
    }
}
