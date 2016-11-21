    <?php

?>

<div class="wrap">
    <h2>Studio Versions</h2>

    <style>
        .all_license td,
        .all_license th {
            padding: 5px 10px;
        }
    </style>

    <?php foreach (Nalpeiron::$mappingStore as $id => $prefix): ?>
        <?php
        $nameStoreCashe = self::$mappingStore[$id];
        $dataStudioVersions =  $this->$nameStoreCashe;?>
        <h3><?= $id ?></h3>
        <form method="POST">
            <input type="hidden" name="shopName" value="<?= $id ?>">
            <input type="submit" name="csv" value="Download CSV">
        </form>
        <table class="all_license">
            <tr>
                <?php
                $titles = ['PurchaseDate', 'OrderNumber', 'Email', 'FirstName', 'LastName', 'StudioVersion', 'UnencryptedLicense', 'Profile Type'];
                foreach ($titles as $item): ?>
                    <th><?php echo $item ?></th>
                <?php endforeach; ?>
            </tr>
            <?php foreach ($dataStudioVersions as $dataStudioVersion) : ?>
                <tr>
                    <td><?= $dataStudioVersion->purchaseDate ?></td>
                    <td><?= $dataStudioVersion->orderId ?></td>
                    <td><?= $dataStudioVersion->email ?></td>
                    <td><?= $dataStudioVersion->firstName ?></td>
                    <td><?= $dataStudioVersion->lastName ?></td>
                    <td><?= $dataStudioVersion->version ?></td>
                    <td><?= $dataStudioVersion->codes ?></td>
                    <td><?= $dataStudioVersion->profile ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>
</div>