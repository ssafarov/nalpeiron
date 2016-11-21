<?php
echo "PurchaseDate,OrderNumber,Email,FirstName,LastName,StudionVersion,UnencryptedLicense,ProfileType"."\r\n";
foreach ($this->dataStudioVersions as $item) {
    $date = [
        $item->purchaseDate,
        $item->orderId,
        $item->email,
        $item->firstName,
        $item->lastName,
        $item->version,
        $item->codes,
        $item->profile,
    ];

    echo implode(', ', $date) . "\r\n";
}
exit;

