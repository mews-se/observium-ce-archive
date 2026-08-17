<?php

// Test-specific setup (bootstrap.php handles common setup)
// Load any specific includes needed for this test suite

class IncludesTemplatesTest extends \PHPUnit\Framework\TestCase
{
  /**
  * @dataProvider providerSimpleTemplate
  * @group simple_template
  */
  public function testSimpleTemplate($template, $keys, $result)
  {
    $this->assertSame($result, simple_template($template, $keys));
  }

  public static function providerSimpleTemplate()
  {
    $return = array(
      // One line php-style comments
      array(
        '<h1>{{title}}</h1>   // just something interesting... #or ^not...',
        array('title' => 'A Comedy of Errors'),
        '<h1>A Comedy of Errors</h1>'
      ),
      // Multiline php-style comments
      array(
        '/**
          * just something interesting... #or ^not...
          */
        <h1>{{title}}</h1>
        /**
          * just something interesting... #or ^not...
          */',
        array('title' => 'A Comedy of Errors'),
        '        <h1>A Comedy of Errors</h1>'.PHP_EOL
      ),
      // Var not exist
      array(
        '<h1>{{title}}</h1>',
        array('non_exist' => 'A Comedy of Errors'),
        '<h1></h1>'
      ),
    );

    $templates_dir = dirname(__FILE__) . '/templates';
    foreach (scandir($templates_dir) as $dir)
    {
      $json = $templates_dir.'/'.$dir.'/'.$dir.'.json';
      if ($dir != '.' && $dir != '..' && is_dir($templates_dir.'/'.$dir) && is_file($json))
      {
        $template = $templates_dir.'/'.$dir.'/'.$dir.'.mustache';
        $result   = $templates_dir.'/'.$dir.'/'.$dir.'.txt';

        $return[] = array(
                      file_get_contents($template),
                      json_decode(file_get_contents($json), TRUE),
                      file_get_contents($result)
                    );
      }
    }

    return $return;
  }

}

// EOF
