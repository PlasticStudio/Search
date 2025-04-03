<?php

namespace PlasticStudio\Search;

use PlasticStudio\Search\SiteTreeSearchExtension;
use SilverStripe\Core\Extension;
use SilverStripe\Versioned\Versioned;

class ElementalSearchExtension extends Extension
{

    /**
     * Force a re-index of the parent page for any given element
     * @param Versioned $original
     */
    public function onAfterPublish(&$original)
    {
        $this->updateSearchContent();
    }

    /**
     * Force a re-index of the parent page on archive of element
     * @param Versioned $original
     */
    public function onAfterDelete()
    {
        $this->updateSearchContent();
    }
    /**
     * Force a re-index of the parent page on un-publish of element
     */
    public function onAfterUnpublish()
    {
        $this->updateSearchContent();
    }

    public function updateSearchContent()
    {
        $parent = $this->getOwner()->getPage();
        if ($parent) {
            //Even though we have the parent page. Lets always get the "live" version. This is so when we update the search content we are not indexing draft/unpublished content
            $liveParentPage = Versioned::get_by_stage($parent->ClassName, Versioned::LIVE)->byID($parent->ID);
            if ($liveParentPage && $liveParentPage->hasExtension(SiteTreeSearchExtension::class)) {
                $liveParentPage->updateSearchContent();
            }
        }
    }
}
