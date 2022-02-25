# Bank of New Zealand BNZ Buyline-CardsAPI

[![Join the chat at https://gitter.im/rpfilomeno/Buyline-CardsAPI](https://badges.gitter.im/rpfilomeno/Buyline-CardsAPI.svg)](https://gitter.im/rpfilomeno/Buyline-CardsAPI?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge) [![Join the chat at https://gitter.im/rpfilomeno/Buyline-CardsAPI](https://badges.gitter.im/rpfilomeno/Buyline-CardsAPI.svg)](https://gitter.im/rpfilomeno/Buyline-CardsAPI?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)


Pure PHP implementation of Buy-Line SWIG API used by BNZ

Do you hate it why BNZ Buy-Line requires you to use SWIG and compile their SDK as PHP module?
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
