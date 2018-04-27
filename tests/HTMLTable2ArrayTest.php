<?php

use PHPUnit\Framework\TestCase;

final class HTMLTable2ArrayTest extends TestCase
{
    private $DataDir = __DIR__ . '/Data/';

    /**
    * @dataProvider providerTable2Array
    * @group local
    */
    public function testTable2Array($file, $json, $params)
    {
        $file_html = $this->DataDir . $file . '.html';
        $file_json = $this->DataDir . $json . '.json';
        if (!is_file($file_html)) {
            $this->markTestSkipped('Testing files not found.');
        }

        $helper = new HTMLTable2Array($params);
        $html = file_get_contents($file_html);
        //var_dump($html);
        $result = $helper->tableToArray(NULL, NULL, $html);

        if (!is_file($file_json)) {
            var_dump($result);
            file_put_contents($file_json, json_encode($result));
            $this->markTestSkipped('Just JSON file created.');
        }
        $test_content = file_get_contents($file_json);

        switch ($params['format']) {
            case 'json':
                $test = $test_content;
                break;
            default:
                $test = json_decode($test_content, TRUE);
        }

        $this->assertSame($result, $test);
    }

    public function providerTable2Array()
    {
        $array = [];
        $params = [];
        $params_json = ['format' => 'json'];

        $array[] = ['simple-table-error', 'simple-table-error',         $params];
        $array[] = ['multiple-tables',    'multiple-tables',            $params];
        $array[] = ['simple-table-error', 'simple-table-error',         $params_json];
        $array[] = ['multiple-tables',    'multiple-tables',            $params_json];
        $array[] = ['multiple-tables',    'multiple-tables-tableAll',   ['tableAll' => TRUE]];
        $array[] = ['multiple-tables',    'multiple-tables-tableAll',   ['tableAll' => TRUE, 'format' => 'json']];
        $array[] = ['multiple-tables',    'multiple-tables-tableID',    ['tableID' => 'test1']];
        $array[] = ['multiple-tables',    'multiple-tables-headerIDs',  ['headerIDs' => FALSE]];

        // Advanced
        $array[] = ['spending-record-finished', 'spending-record-finished',     $params];
        $array[] = ['hidden-rows',              'hidden-rows',                  $params];
        $array[] = ['hidden-rows',              'hidden-rows-ignoreHidden',     ['ignoreHidden' => TRUE]];
        $array[] = ['timetable-caption',        'timetable-caption',            $params];
        $array[] = ['simple-table-error',       'simple-table-ignoreColumns1',  ['ignoreColumns' => ['IP', 'OS']]];
        $array[] = ['simple-table-error',       'simple-table-ignoreColumns2',  ['ignoreColumns' => [3, 1]]];
        $array[] = ['simple-table-error',       'simple-table-onlyColumns1',    ['onlyColumns' => ['IP', 'OS']]];
        $array[] = ['simple-table-error',       'simple-table-onlyColumns2',    ['onlyColumns' => [3, 1]]];

        return $array;
    }

}
