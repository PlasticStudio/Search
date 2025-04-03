<?php

namespace PlasticStudio\Search;

use SilverStripe\Dev\BuildTask;
use SilverStripe\View\SSViewer;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\Queries\SQLUpdate;

class IndexPageContentForSearchTask extends BuildTask
{
    protected $title = 'Index Page Content for Search';
 
    protected $description = 'Collate all page content from elements and save to a field for search. Add optional query string, "reindex=true" to reindex all pages.';
 
    public function run($request)
    {
        $reindex = $request->getVar('reindex');
        $offset = $request->getVar('offset') ? $request->getVar('offset') : NULL;
        $limit = $request->getVar('limit') ? $request->getVar('limit') : 10;

        // select all sitetree items
        $items = SiteTree::get()->limit($limit, $offset);
        echo 'Running...<br />';
        echo 'limit: ' . $limit . '<br />';
        echo 'offset: ' . $offset . '<br />';
        // echo 'count ' . $items->Count(). '<br />';

        if(!$reindex) {
            $items = $items->filter(['ElementalSearchContent' => null]);
            echo 'Running - generating first index...<br />';
        }

        if(!$items->count()) {
            echo 'No items to update.<br />';
        } else {

            foreach ($items as $item) {
                
                // get the page content as plain content string
                $content = $this->collateSearchContent($item);

                // Update this item in db
                $update = SQLUpdate::create();
                $update->setTable('"SiteTree"');
                $update->addWhere(['ID' => $item->ID]);
                $update->addAssignments([
                    '"ElementalSearchContent"' => $content
                ]);
                $update->execute();

                // IF page is published, update the live table
                if ($item->isPublished()) {
                    $update = SQLUpdate::create();
                    $update->setTable('"SiteTree_Live"');
                    $update->addWhere(['ID' => $item->ID]);
                    $update->addAssignments([
                        '"ElementalSearchContent"' => $content
                    ]);
                    $update->execute();
                }

                echo '<p>Page ' . $item->Title . ' indexed.</p>' . PHP_EOL;
            }
        }
    }

    /**
     * Generate the search content to use for the searchable object
     *
     * We just retrieve it from the templates.
     */
    private function collateSearchContent($page): string
    {
        // Get the page
        /** @var SiteTree $page */
        // $page = $this->getOwner();

        $content = '';

        if (self::isElementalPage($page)) {
            // Get the page's elemental content
            $content .= $this->collateSearchContentFromElements($page);
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
    private function collateSearchContentFromElements($page)
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
            $content .= $page->getOwner()->getElementsForSearch();

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