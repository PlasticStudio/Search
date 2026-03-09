<?php

namespace PlasticStudio\Search;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\SSViewer;
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\Queries\SQLUpdate;
use DNADesign\Elemental\Extensions;

class SiteTreeSearchExtension extends DataExtension
{

    /**
     * @var array
     */
    private static $db = [
        'ElementalSearchContent' => 'Text',
    ];

    /**
     * preload cache for batch publishing
     * @var null|array
     */
    private static ?array $elementalAreasCache = null;

    public function updateCMSFields(FieldList $fields)
    {
        // $fields->addFieldToTab('Root.test', TextField::create('ElementalSearchContent', 'ElementalSearchContent'));
    }

    /**
     * Trigger page writes so that we trigger the onBefore write
     */
    public function updateSearchContent()
    {
        $content = $this->collateSearchContent();

        $update = SQLUpdate::create();
        $update->setTable('"SiteTree"');
        $update->addWhere(['ID' => $this->owner->ID]);
        $update->addAssignments([
            '"ElementalSearchContent"' => $content
        ]);
        $update->execute();

        if ($this->owner->isInDB() && $this->owner->isPublished()) {
            $update = SQLUpdate::create();
            $update->setTable('"SiteTree_Live"');
            $update->addWhere(['ID' => $this->owner->ID]);
            $update->addAssignments([
                '"ElementalSearchContent"' => $content
            ]);
            $update->execute();
        }
    }

    /**
     * Generate the search content to use for the searchable object
     *
     * We just retrieve it from the templates.
     */
    private function collateSearchContent(): string
    {
        // Get the page
        /** @var SiteTree $page */
        $page = $this->getOwner();

        $content = '';

        if (self::isElementalPage($page)) {
            // Get the page's elemental content
            $content .= $this->collateSearchContentFromElements();
        }

        return $content;
    }


    /**
     * @param SiteTree $page
     * @return bool
     */
    private static function isElementalPage($page)
    {
        return $page::has_extension("DNADesign\Elemental\Extensions\ElementalPageExtension");
    }

    /**
     * @return string|string[]|null
     */
    private function collateSearchContentFromElements()
    {
        // Get the original theme
        $originalThemes = SSViewer::get_themes();

        // Init content
        $content = '';

        try {
            Config::nest();
            SSViewer::set_themes(SSViewer::config()->get('themes'));

            $page = $this->getOwner();

            // Optional preload for bulk indexing
            if (Config::inst()->get(self::class, 'preload_elemental_areas')) {
                self::preloadElementalAreas();
            }

            if ($page->hasMethod('getElementsForSearch')) {
                $content .= $page->getElementsForSearch();
            } elseif ($page->hasExtension(ElementalPageExtension::class)) {
                $content .= $this->getElementsForSearchFromElemental($page);
            }

            $content = preg_replace('/\s+/', ' ', $content);

            Config::unnest();
        } finally {
            SSViewer::set_themes($originalThemes);
        }

        return $content;
    }



    protected function getElementsForSearchFromElemental(SiteTree $page): string
    {
        $output = [];
        $seen = [];

        // Collect all areas for this page
        $areas = [];
        foreach ($page->hasOne() as $relation => $class) {
            if (!is_a($class, ElementalArea::class, true)) {
                continue;
            }

            $areaID = $page->{"{$relation}ID"};
            if ($areaID && isset(self::$elementalAreasCache[$areaID])) {
                $areas[$areaID] = self::$elementalAreasCache[$areaID];
            } elseif ($page->$relation()->exists()) {
                $areas[$areaID] = $page->$relation();
            }
        }

        // Collect all element IDs and fetch them in one query
        $elementIDs = [];
        foreach ($areas as $area) {
            foreach ($area->Elements()->column('ID') as $id) {
                $elementIDs[$id] = $id;
            }
        }

        if (!$elementIDs) {
            return '';
        }

        $elements = BaseElement::get()->filter('ID', array_values($elementIDs));

        foreach ($elements as $element) {
            if (isset($seen[$element->ID])) {
                continue;
            }
            $seen[$element->ID] = true;

            if (!$element->getSearchIndexable()) {
                continue;
            }

            $content = $element->getContentForSearchIndex();

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

        return implode(' ', $output);
    }

    /**
     * Preload ElementalAreas for batch processing
     */
    protected static function preloadElementalAreas(): void
    {
        if (self::$elementalAreasCache === null) {
            self::$elementalAreasCache = [];
            foreach (ElementalArea::get()->eagerLoad('Elements') as $area) {
                self::$elementalAreasCache[$area->ID] = $area;
            }
        }
    }

    protected static function resetElementalCache(): void
    {
        self::$elementalAreasCache = null;
    }
}
