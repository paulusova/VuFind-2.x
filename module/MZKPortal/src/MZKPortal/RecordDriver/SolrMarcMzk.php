<?php
namespace MZKPortal\RecordDriver;
use MZKCommon\RecordDriver\SolrMarc;

class SolrMarcMzk extends SolrMarc
{
	public function getEOD()
	{
		$eod = $this->getFirstFieldValue('EOD', array('a'));
		return ($eod == 'Y')?true:false;
	}
}