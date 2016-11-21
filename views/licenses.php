<div class="wrap">
    <h2></h2>

    <h2>Licenses</h2>

    <style>
        .all_license td,
        .all_license th {
            padding: 5px 10px;
        }
    </style>

    <?php
    $sites = [
        'ROW' => 'fu_',
        'US' => 'fu_4_',
    ];
    ?>

    <?php foreach ($sites as $id => $prefix): ?>
        <h3><?= $id ?></h3>
        <?php
        global $wpdb;
        $result = $wpdb->get_results("
SELECT
codes as `license`,
title as `product`,
order_id as `order`,
order_user_id as `user`,
order_date as `date`
FROM `{$prefix}license` ");
        ?>
        <table class="all_license">
            <tr>
                <?php foreach ((array)$result[0] as $h => $item): ?>
                    <th><?= $h ?></th>
                <?php endforeach; ?>
            </tr>
            <?php foreach ($result as $items) : ?>
                <tr>
                    <?php foreach ((array)$items as $item): ?>
                        <td><?= $item ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>

</div>