<?php

class TOPCONTENT_CUSTOM_HTML
{
    const H2_REGEXP = '/(<h2>.*?<\/h2>)/si';

    const SUBHEADING_REGEXP = '/(<h[2-6]>.*?<\/h[2-6]>)/si';

    const PARAGRAPH_REGEXP = '/(<p>.*?<\/p>)/si';

    function handle($result, $custom_html)
    {
        foreach ($custom_html as $block) {
            if (strtolower($block->position) === 'top') {
                $result['body'] = $block->html.PHP_EOL.$result['body'];
                continue;
            }

            if (strtolower($block->position) === 'end') {
                $result['body'] .= PHP_EOL.$block->html;
                continue;
            }

            if (stripos($block->position, 'after:paragraph:') !== false) {
                $result['body'] = $this->handleAfterParagraphX($result['body'], $block);
            }

            if (
                stripos($block->position, 'after:h2:') !== false
                || stripos($block->position, 'before:h2:') !== false
            ) {
                $result['body'] = $this->handleHeadings($result['body'], $block, self::H2_REGEXP);
            }

            if (
                stripos($block->position, 'after:subheading:') !== false
                || stripos($block->position, 'before:subheading:') !== false
            ) {
                $result['body'] = $this->handleHeadings($result['body'], $block, self::SUBHEADING_REGEXP);
            }
        }

        return $result;
    }

    private function handleAfterParagraphX($postBody, $block)
    {
        $params = array_map(
            'trim',
            explode(':', $block->position)
        );

        $afterParagraphNumber = (int) $params[2];

        preg_match_all(self::PARAGRAPH_REGEXP, $postBody, $matches);

        foreach ($matches[1] as $index => $paragraph) {
            if (($index + 1) === $afterParagraphNumber) {
                $changedParagraph = $paragraph.PHP_EOL.$block->html;
                return str_ireplace($paragraph, $changedParagraph, $postBody);
            }
        }

        return $postBody;
    }

    private function handleHeadings($postBody, $block, $regexp)
    {
        $params = array_map(
            'trim',
            explode(':', $block->position)
        );

        $modifyBlockNumber = (int) $params[2];
        $position = $params[0];

        preg_match_all($regexp, $postBody, $matches);

        foreach ($matches[1] as $index => $heading) {
            if (($index + 1) === $modifyBlockNumber) {
                $changedHeading = $position === 'before'
                    ? $block->html.PHP_EOL.$heading
                    : $heading.PHP_EOL.$block->html;

                return str_ireplace($heading, $changedHeading, $postBody);
            }
        }

        return $postBody;
    }
}
