<?php
namespace MZKCommon\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array(
            'ils_driver' => array(
                'factories' => array(
                    'aleph' => 'MZKCommon\ILS\Driver\Factory::getAleph',
                ), /* factories */
            ), /* ils_drivers */
            'recommend' => array(
                'factories' => array(
                    'specifiablefacets' => 'MZKCommon\Recommend\Factory::getSpecifiableFacets',
                ), /* factories */
            ), /* recommend */
            'recorddriver' => array (
                'factories' => array(
                    'solrdefault' => 'MZKCommon\RecordDriver\Factory::getSolrMarc',
                ) /* factories */
            ), /* recorddriver */
            'recordtab' => array(
                'abstract_factories' => array('VuFind\RecordTab\PluginFactory'),
                'invokables' => array(
                    'holdingsils'     => 'MZKCommon\RecordTab\HoldingsILS',
                    'tagsandcomments' => 'MZKCommon\RecordTab\TagsAndComments',
                ), /* invokables */
            ), /* recordtab */
            'db_table' => array(
                'invokables' => array(
                    'recordstatus' => 'MZKCommon\Db\Table\RecordStatus',
                ),
            ),
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'VuFind\ILSHoldLogic' => 'MZKCommon\ILS\Logic\Factory::getFlatHolds',
        ),
    ),
    'controllers' => array(
        'factories' => array(
            'record' => 'MZKCommon\Controller\Factory::getRecordController',
        ),
        'invokables' => array(
            'search'     => 'MZKCommon\Controller\SearchController',
            'ajax'       => 'MZKCommon\Controller\AjaxController',
            'myresearch' => 'MZKCommon\Controller\MyResearchController',
        ),
    ),
    'controller_plugins' => array(
        'factories' => array(
            'shortLoanRequests'   => 'MZKCommon\Controller\Plugin\Factory::getShortLoanRequests',
        ),
    ),
);

$staticRoutes = array(
    'Search/Conspectus', 'Search/MostSearched', 'Search/NewAcquisitions',
    'MyResearch/CheckedOutHistory', 'MyResearch/ShortLoans',
    'MyResearch/FavoritesImport', 'MyResearch/ProfileChange',
);

foreach ($staticRoutes as $route) {
    list($controller, $action) = explode('/', $route);
    $routeName = str_replace('/', '-', strtolower($route));
    $config['router']['routes'][$routeName] = array(
        'type' => 'Zend\Mvc\Router\Http\Literal',
        'options' => array(
            'route'    => '/' . $route,
            'defaults' => array(
                'controller' => $controller,
                'action'     => (! empty($action)) ? $action : 'default',
            )
        )
    );
}

$nonTabRecordActions = array('ShortLoan');

foreach ($nonTabRecordActions as $action) {
    $config['router']['routes']['record' . '-' . strtolower($action)] = array(
        'type'    => 'Zend\Mvc\Router\Http\Segment',
        'options' => array(
            'route'    => '/' . 'Record' . '/[:id]/' . $action,
            'constraints' => array(
                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            ),
            'defaults' => array(
                'controller' => 'Record',
                'action'     => $action,
            )
        )
    );
}

return $config;
