<?php

class LinkingRules
{
    const RULES_CATEGORIES = 'topcont-rules-categories';
    const RULES_PHRASES = 'topcont-rules-phrases';
    const RULES_URLS = 'topcont-rules-urls';

    const ANY_CATEGORY_ID = 0;
    const ANCHOR_PHRASE_CHARACTERS_MIN = 1;

    public static function hasRules()
    {
        return get_option(self::RULES_CATEGORIES) && get_option(self::RULES_PHRASES) && get_option(self::RULES_URLS);
    }

    public static function getRules($key)
    {
        return unserialize(get_option($key, []));
    }

    public static function updateRules()
    {
        if (isset($_POST[self::RULES_CATEGORIES]) && is_array($_POST[self::RULES_CATEGORIES])
            && isset($_POST[self::RULES_PHRASES]) && is_array($_POST[self::RULES_PHRASES])
            && isset($_POST[self::RULES_URLS]) && is_array($_POST[self::RULES_URLS])) {

            $categories = array_values($_POST[self::RULES_CATEGORIES]);
            $phrases = array_values($_POST[self::RULES_PHRASES]);
            $urls = array_values($_POST[self::RULES_URLS]);

            array_walk_recursive($categories, function (&$value) use ($categories) {
                $value = (int)$value;
            });
            array_walk_recursive($phrases, function (&$value) {
                $value = wp_kses_data($value);
                $value = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $value);
            });
            array_walk_recursive($urls, function (&$value) {
                $value = wp_kses_data($value);
            });

            foreach ($categories as $k => $cats) {
                foreach ($cats as $k2 => $cat) {
                    $c = (int)$categories[$k][$k2];

                    if ($c != self::ANY_CATEGORY_ID) {
                        $wpCategory = get_category($c);

                        if (!$wpCategory) {
                            unset($categories[$k][$k2]);
                        } else {
                            $categories[$k][$k2] = $wpCategory->name;
                        }
                    }
                }
            }

            update_option(self::RULES_CATEGORIES, serialize($categories));
            update_option(self::RULES_PHRASES, serialize($phrases));
            update_option(self::RULES_URLS, serialize($urls));
        } else {
            delete_option(self::RULES_CATEGORIES);
            delete_option(self::RULES_PHRASES);
            delete_option(self::RULES_URLS);
        }
    }

    public static function buildWpCategoriesList($options = array())
    {
        $args = array(
            'taxonomy'         => 'category',
            'orderby'          => 'name',
            'hide_empty'       => false,
            'hierarchical'     => true,
        );

        $categories = get_terms($args);

        $selected = (isset($options['selected'])) ? $options['selected'] : [];
        $required = (isset($options['is_required']) && $options['is_required'] == true) ? 'required' : '';
        $name = (isset($options['dropdown_name'])) ? esc_attr($options['dropdown_name']) : 'topcont_categories[]';
        $class = (isset($options['dropdown_class'])) ? esc_attr($options['dropdown_class']) : 'topcont-categories-dropdown topcont-w-100 topcont-h-100px';
        $showOptionAllText = (isset($options['option_all_text'])) ? esc_attr($options['option_all_text']) : 'Any';

        $output = '<select name="' . $name . '" class="' . $class . '" multiple ' . $required . '>';

        $output .= '<option value="0"';
        if (in_array(0, $selected)) {
            $output .= ' selected';
        }
        $output .= '>' . esc_html($showOptionAllText) . '</option>';

        self::buildCategoryDropdownOptions($categories, $selected, $output);

        $output .= '</select>';

        return $output;
    }

    private static function buildCategoryDropdownOptions($categories, $selected, &$output, $parent = 0, $level = 0)
    {
        foreach ($categories as $category) {
            if ($category->parent == $parent) {
                $output .= '<option value="' . esc_attr($category->term_id) . '"';
                if (in_array($category->name, $selected)) {
                    $output .= ' selected';
                }
                $output .= '>' . str_repeat('&nbsp;', $level * 4) . esc_html($category->name) . ' (' . esc_html($category->count) . ')</option>';
                self::buildCategoryDropdownOptions($categories, $selected, $output, $category->term_id, $level + 1);
            }
        }
    }

    public static function applyRules($postContent, $postCategories = array())
    {
        if ($postCategories) {
            $categories = self::getRules(self::RULES_CATEGORIES);
            $phrases = self::getRules(self::RULES_PHRASES);
            $urls = self::getRules(self::RULES_URLS);

            foreach ($categories as $k => $cats) {
                foreach ($cats as $cat) {
                    if ($cat == self::ANY_CATEGORY_ID) {
                        $postContent = self::applyRulesToContent($postContent, $phrases[$k][0], $urls[$k][0]);
                    } else {
                        $cat = get_cat_ID($cat);

                        if ($cat && in_array($cat, $postCategories)) {
                            $postContent = self::applyRulesToContent($postContent, $phrases[$k][0], $urls[$k][0]);
                        }
                    }
                }
            }
        }

        return $postContent;
    }

    /**
     * Rules:
     * It ignores words that are already in links.
     * It only makes links for words in paragraphs of text.
     * It avoids making links in alt tags, titles, meta descriptions, or subheadings.
     * It does not make links for texts in subheadings.
     */
    private static function applyRulesToContent($content, $phrases, $url)
    {
        $phrases = explode("\n", $phrases);
        $phrases = array_filter($phrases, function ($value) {
            return $value !== "";
        });

        $pattern = '/(<p[^>]*>)(.*?)(<\/p>)/si';

        foreach ($phrases as $phrase) {
            if (mb_strlen($phrase) >= self::ANCHOR_PHRASE_CHARACTERS_MIN) {
                $exit = false;

                $content = preg_replace_callback($pattern, function ($matches) use ($phrase, $url, &$exit) {
                    $paragraph = $matches[2];

                    $paragraph = preg_replace_callback('/<a[^>]*>.*?<\/a>(*SKIP)(*F)|\b' . preg_quote($phrase, '/') . '\b/i', function ($match) use ($phrase, $url, &$exit) {
                        $exit = true;
                        return '<a href="' . $url . '">' . $phrase . '</a>';
                    }, $paragraph);

                    return $matches[1] . $paragraph . '</p>';
                }, $content);

                if ($exit) {
                    break;
                }
            }
        }

        return $content;
    }
}
