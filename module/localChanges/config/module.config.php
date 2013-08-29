<?php
namespace localChanges\Module\Configuration;

$config = array(
		'vufind' => array(
				'plugin_managers' => array(
						'recordtab' => array(
								'invokables' => array(
										'test' => 'localChanges\RecordTab\Test',
								),
						)
				),
				'recorddriver_tabs' => array(
						'VuFind\RecordDriver\SolrMarc' => array(
			                'tabs' => array(
			                    'Test' => 'Test'
			                 ),
            			),
				) /* recorddriver_tabs */
		)
);

return $config;