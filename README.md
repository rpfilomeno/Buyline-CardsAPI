# Buyline-CardsAPI
Pure PHP implementation of Buy-Line SWIG API

Do you hate it why Buy-Line requires you to use SWIG and compile their SDK as PHP module?
Do you hate it when they haven't forked it to support PHP7?
Here is your answer, a PHP Class that implements the API is pure PHP sockets.

## Example

```php
require_once "Buyline_CardsAPI.php";

$buyline = new Buyline_CardsAPI();

//connection details
$buyline->setHost("trans.buylineplus.co.nz");
$buyline->setPort("3008");
$buyline->setTimeout(5);

//authentication
$buyline->setClientId("10000000");
$buyline->setPemFile("BNZTest.cer");
$buyline->setPassPhrase("You certificate password");

//debug setting
$buyline->setDebug(true);
$buyline->setVerbose(true);
$buyline->setLogfile("webpay.log");

//transaction

try {
    $buyline->purchase("4564456445644564", "1020", "10", "4564");
    echo 'Transaction Code: '. $buyline->getResponseCode(). "\n";
    echo 'Transaction Text: '. $buyline->getResponseText(). "\n";
}catch (Exception $e) {
    echo 'Caught exception: '.  $e->getMessage(). "\n";
}

$x = $buyline->getResult();
var_dump($x);
```
