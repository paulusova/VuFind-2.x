<?php

/**
 * Solr spelling listener.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Solr;

use VuFindSearch\Service;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Solr\Response\Json\Spellcheck;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;

/**
 * Solr spelling listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class InjectSpellingListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Is spelling active?
     *
     * @var bool
     */
    protected $active = false;

    /**
     * Dictionaries for spellcheck.
     *
     * @var array
     */
    protected $dictionaries;

    /**
     * Logger.
     *
     * @var Zend\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param BackendInterface $backend      Backend
     * @param array            $dictionaries Spelling dictionaries to use.
     *
     * @return void
     */
    public function __construct(BackendInterface $backend, array $dictionaries)
    {
        $this->backend = $backend;
        $this->dictionaries = $dictionaries;
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach(
            'VuFind\Search', Service::EVENT_PRE, [$this, 'onSearchPre']
        );
        $manager->attach(
            'VuFind\Search', Service::EVENT_POST, [$this, 'onSearchPost']
        );
    }

    /**
     * Set up spelling parameters.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        if ($event->getParam('context') != 'search') {
            return $event;
        }
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            $params = $event->getParam('params');
            if ($params) {
                // Set spelling parameters when enabled:
                $sc = $params->get('spellcheck');
                if (isset($sc[0]) && $sc[0] != 'false') {
                    $this->active = true;
                    if (empty($this->dictionaries)) {
                        throw new \Exception(
                            'Spellcheck requested but no dictionary configured'
                        );
                    }

                    // Set relevant Solr parameters:
                    reset($this->dictionaries);
                    $params->set('spellcheck', 'true');
                    $params->set(
                        'spellcheck.dictionary', current($this->dictionaries)
                    );

                    // Turn on spellcheck.q generation in query builder:
                    $this->backend->getQueryBuilder()
                        ->setCreateSpellingQuery(true);
                }
            }
        }
        return $event;
    }

    /**
     * Inject additional spelling suggestions.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        // Do nothing if spelling is disabled or context is wrong
        if (!$this->active || $event->getParam('context') != 'search') {
            return $event;
        }

        // Merge spelling details from extra dictionaries:
        $backend = $event->getParam('backend');
        if ($backend == $this->backend->getIdentifier()) {
            $result = $event->getTarget();
            $params = $event->getParam('params');
            $spellcheckQuery = $params->get('spellcheck.q');
            if (!empty($spellcheckQuery)) {
                $this->aggregateSpellcheck(
                    $result->getSpellcheck(), end($spellcheckQuery)
                );
            }
        }
    }

    /**
     * Submit requests for more spelling suggestions.
     *
     * @param Spellcheck $spellcheck Aggregating spellcheck object
     * @param string     $query      Spellcheck query
     *
     * @return void
     */
    protected function aggregateSpellcheck(Spellcheck $spellcheck, $query)
    {
        while (next($this->dictionaries) !== false) {
            $params = new ParamBag();
            $params->set('spellcheck', 'true');
            $params->set('spellcheck.dictionary', current($this->dictionaries));
            $queryObj = new Query($query, 'AllFields');
            try {
                $collection = $this->backend->search($queryObj, 0, 0, $params);
                $spellcheck->mergeWith($collection->getSpellcheck());
            } catch (\Exception $ex) {
                if ($this->logger) {
                    $this->logger->err("Exception thrown when aggregating spellcheck, ignoring.",
                        array ('exception' => $ex));
                }
            }
        }
    }

    /**
     * Set the Logger.
     *
     * @param VuFind\Log\Logger $logger Logger
     *
     * @return void
     */
    public function setLogger(\VuFind\Log\Logger $logger)
    {
        $this->logger = $logger;
    }
}
