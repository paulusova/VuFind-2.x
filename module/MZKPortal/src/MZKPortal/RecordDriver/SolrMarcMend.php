<?php
namespace MZKPortal\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc;

class SolrMarcMend extends SolrMarcBase
{
    public function getInstitutionsWithIds() {
        
    }

    protected function getExternalID() {
        return substr($this->getLocalId(), 5);
    }
}
