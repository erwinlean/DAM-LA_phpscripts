<?php
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

$now = date('Y/m/d H:i:s', time());
$log  = $now . ' Logging script copy process' . PHP_EOL;
file_put_contents(logEtradeFILE, $log, FILE_APPEND);

use Guzzle\Http\Client;

$serverUrl = assetsURL;

// Log In
$service = '/services/apilogin?';

$client = new GuzzleHttp\Client();

$params = [];
$params['username'] = assetsUSERNAME;
$params['password'] = assetsPASSWORD;

$response = $client->request('POST', $serverUrl . $service . http_build_query($params));
$jsonResponse = json_decode($response->getBody());
$authToken = $jsonResponse->authToken;

$yesterday = strtotime("yesterday");
$yesterday = date('Y-m-d', $yesterday);
$yesterdayQuery = '[' . $yesterday . 'T00:00:00' . ' TO ' . $yesterday . 'T23:59:59]';

// Search assets
$sservice = '/services/search?';

// Assets from "Etiquetado" to "Etrade"
$sclient = new GuzzleHttp\Client();

$sparams = [];
//$sparams['q'] = 'ancestorPaths:"' . assetsCOPY_SOURCE__PATH . '"assetCreated:' . $yesterdayQuery;
$sparams['q'] = 'ancestorPaths:"' . assetsCOPY_SOURCE__PATH . '"';// > without date, so copy every day everything. Need to modify the script, so doesnt copy if the asset already exist in the path to copy
$sparams['num'] = 0;

$headers = [];
$headers['Authorization'] = 'Bearer ' . $authToken;

$srequest = $sclient->request('POST', $serverUrl . $sservice . http_build_query($sparams) , ['headers' => $headers]);
$sjsonResponse = json_decode($srequest->getBody());

$totalHits = $sjsonResponse->totalHits;

$now = date('Y/m/d H:i:s', time());
$log  = $now . ' About to copy ' . $totalHits . ' assets from ' . assetsCOPY_SOURCE__PATH . PHP_EOL;
file_put_contents(logEtradeFILE, $log, FILE_APPEND);

for($i=0; $i<$sjsonResponse->totalHits; $i+=50)
{
    $s2service = '/services/search?';

    $s2client = new GuzzleHttp\Client();

    $s2params = [];
    // $sparams['q'] = 'ancestorPaths:"' . assetsCOPY_SOURCE__PATH . '"assetCreated:' . $yesterdayQuery;
    $s2params['q'] = 'ancestorPaths:"' . assetsCOPY_SOURCE__PATH . '"'; // > without date, so copy every day everything. Need to modify the script, so doesnt copy if the asset already exist in the path to copy
    $s2params['start'] = $i;
    $s2params['metadataToReturn'] = 'assetCreated';

    $headers = [];
    $headers['Authorization'] = 'Bearer ' . $authToken;

    $s2request = $s2client->request('POST', $serverUrl . $s2service . http_build_query($s2params) , ['headers' => $headers]);
    $s2jsonResponse = json_decode($s2request->getBody());

    foreach($s2jsonResponse->hits as $hit)
    {
        // if (preg_match('/\.(png|jpg|JPG|JPEG|TIFF|tiff|tif|eps|psd)$/', $hit->metadata->filename) && preg_match('/\d{7,}/', $hit->metadata->filename))  > modified to check if the filename have at least 7 numbers as a string
        if (preg_match('/\.(png|jpg|JPG|JPEG|TIFF|tiff|tif|eps|psd)$/', $hit->metadata->filename) && preg_match('/\D*(\d\D*){7,}/', $hit->metadata->filename))
        {
            // Copy
            $cservice = '/services/copy?';
        
            $cclient = new GuzzleHttp\Client();
        
            $cparams = [];
            $cparams['source'] = $hit->metadata->assetPath;
            $cparams['target'] = etradeAssetsCOPY_TARGET__PATH . '/' . $hit->metadata->filename;
        
            $crequest = $cclient->request('POST', $serverUrl . $cservice . http_build_query($cparams) , ['headers' => $headers]);
            $cjsonResponse = json_decode($crequest->getBody());
        
            if($cjsonResponse->processedCount === 1)
            {
                $now = date('Y/m/d H:i:s', time());
                $log  = $now . ' Copied asset ' . $hit->metadata->assetPath . PHP_EOL;
                file_put_contents(logEtradeFILE, $log, FILE_APPEND);
            }
        }
        
    }
}



// Assets from "Carga general" to "Etrade"
$sclient = new GuzzleHttp\Client();

$sparams = [];
//$sparams['q'] = 'ancestorPaths:"' . assetsCOPY_TARGET__PATH . '"assetCreated:' . $yesterdayQuery;
$sparams['q'] = 'ancestorPaths:"' . assetsCOPY_TARGET__PATH . '"'; //> without date, so copy every day everything. Need to modify the script, so doesnt copy if the asset already exist in the path to copy
$sparams['num'] = 0;

$headers = [];
$headers['Authorization'] = 'Bearer ' . $authToken;

$srequest = $sclient->request('POST', $serverUrl . $sservice . http_build_query($sparams) , ['headers' => $headers]);
$sjsonResponse = json_decode($srequest->getBody());

$totalHits = $sjsonResponse->totalHits;

$now = date('Y/m/d H:i:s', time());
$log  = $now . ' About to copy ' . $totalHits . ' assets from ' . assetsCOPY_TARGET__PATH . PHP_EOL;
file_put_contents(logEtradeFILE, $log, FILE_APPEND);

for($i=0; $i<$sjsonResponse->totalHits; $i+=50)
{
    $s2service = '/services/search?';

    $s2client = new GuzzleHttp\Client();

    $s2params = [];
    //$sparams['q'] = 'ancestorPaths:"' . assetsCOPY_TARGET__PATH . '"assetCreated:' . $yesterdayQuery;
    $s2params['q'] = 'ancestorPaths:"' . assetsCOPY_TARGET__PATH . '"'; //> without date, so copy every day everything. Need to modify the script, so doesnt copy if the asset already exist in the path to copy
    $s2params['start'] = $i;
    $s2params['metadataToReturn'] = 'assetCreated';

    $headers = [];
    $headers['Authorization'] = 'Bearer ' . $authToken;

    $s2request = $s2client->request('POST', $serverUrl . $s2service . http_build_query($s2params) , ['headers' => $headers]);
    $s2jsonResponse = json_decode($s2request->getBody());

    foreach($s2jsonResponse->hits as $hit)
    {
        if (preg_match('/\.(png|jpg|JPG|JPEG|TIFF|tiff|tif|eps|psd)$/', $hit->metadata->filename) && preg_match('/\d{7,}/', $hit->metadata->filename)) 
        {
            // Copy
            $cservice = '/services/copy?';
        
            $cclient = new GuzzleHttp\Client();
        
            $cparams = [];
            $cparams['source'] = $hit->metadata->assetPath;
            $cparams['target'] = etradeAssetsCOPY_TARGET__PATH . '/' . $hit->metadata->filename;
        
            $crequest = $cclient->request('POST', $serverUrl . $cservice . http_build_query($cparams) , ['headers' => $headers]);
            $cjsonResponse = json_decode($crequest->getBody());
        
            if($cjsonResponse->processedCount === 1)
            {
                $now = date('Y/m/d H:i:s', time());
                $log  = $now . ' Copied asset ' . $hit->metadata->assetPath . PHP_EOL;
                file_put_contents(logEtradeFILE, $log, FILE_APPEND);
            }
        }
    }
}

// END >>> Log Out
$lservice = '/services/logout';

$lclient = new GuzzleHttp\Client();

$lrequest = $lclient->request('POST', $serverUrl . $lservice, ['headers' => $headers]);
$ljsonResponse = json_decode($lrequest->getBody());

$now = date('Y/m/d H:i:s', time());
$log  = $now . ' Script finished' . PHP_EOL;
file_put_contents(logEtradeFILE, $log, FILE_APPEND);
?>