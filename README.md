# Limesurvey-AuditYiiLogger
Audit log of changes using Yii logging framework.

To enable edit config.php to add the log component configuration and preload the log module.
E.g.

    return array(
      'components' => array(
        // here there are db, session, urlManager, config components etc
        // [ snip ]
        'log'=>array(
          'class' => 'CLogRouter',
            'routes' => array(
              array(
                'class'=>'CFileLogRoute',
                'categories'=>'application.Audit',
                'logPath'=>'/var/log/limesurvey/',
                'logFile'=>'audit.log',
              ),
            ),
         ),
      ),
     'preload' => array('log'),
    );

For further information on Yii logging see http://www.yiiframework.com/doc/guide/1.1/it/topics.logging
