<?php

namespace PlasticStudio\Search;

use DNADesign\Elemental\Extensions\ElementalPageExtension;

class CustomElementalPageExtension extends ElementalPageExtension {


/*************
 * add isBlockIndexable exclude from search
 * CHECK VIRTUAL ELEMENTS AS WELL
 */

    public function getElementsForSearch()
    {
        $oldThemes = SSViewer::get_themes();
        SSViewer::set_themes(SSViewer::config()->get('themes'));

        try {
            $output = [];
            $elements = [];
            $seen = [];

            // Allow projects to override element loading
            if ($this->owner->hasMethod('getEagerLoadedElements')) {
                $elements = $this->owner->getEagerLoadedElements();
            } else {
                $elements = $this->getEagerLoadedElements();
            }

            /** @var BaseElement $element */
            foreach ($elements as $element) {

                // Deduplicate elements
                if (isset($seen[$element->ID])) {
                    continue;
                }
                
                $seen[$element->ID] = true;

                // if (!$element->isBlockIndexable()) continue;
                if (!$element->getSearchIndexable()) continue;

                $content = $element->getContentForSearchIndex();

                // Support custom search content (vue template etc)
                // add function to retrieve custom search content to element class
                if ($element->hasMethod('getCustomSearchContent')) {
                    $custom = $element->getCustomSearchContent();
                    if ($custom) {
                        $content .= ' ' . $custom;
                    }
                }

                if ($content) {
                    $output[] = $content;
                }
            }

        } finally {
            SSViewer::set_themes($oldThemes);
        }

        return implode(
            $this->owner->config()->get('search_index_element_delimiter') ?? '',
            $output
        );
    }


}