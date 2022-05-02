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

class SiteTreeSearchExtension extends DataExtension
{

    /**
     * @var array
     */
    private static $db = [
        'ElementalSearchContent' => 'Text',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab('Root.test', TextField::create('ElementalSearchContent', 'ElementalSearchContent'));
    }

    /**
     * Trigger page writes so that we trigger the onBefore write
     */
    public function updateSearchContent(){

        $content = $this->collateSearchContent();

        $update = SQLUpdate::create();
        $update->setTable('"SiteTree"');
        $update->addWhere(['ID' => $this->owner->ID]);
        $update->addAssignments([
            '"ElementalSearchContent"' => $content
        ]);
        $update->execute();

        if($this->owner->isInDB() && $this->owner->isPublished()){

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
            // Enable frontend themes in order to correctly render the elements as they would be for the frontend
            Config::nest();
            SSViewer::set_themes(SSViewer::config()->get('themes'));

            // Get the elements content
            $content .= $this->getOwner()->getElementsForSearch();

            // Clean up the content
            $content = preg_replace('/\s+/', ' ', $content);

            // Return themes back for the CMS
            Config::unnest();
        } finally {
            // Restore themes
            SSViewer::set_themes($originalThemes);
        }

        return $content;
    }


}