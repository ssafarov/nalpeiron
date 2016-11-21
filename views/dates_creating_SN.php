<?php
$namField = 'dates_create_serial_numbers';
?>
<style>
    .dates-serial-numbers td,
    .dates-serial-numbers th {
        padding: 5px;
    }
</style>
<h1>Dates Creating Serial numbers</h1>
<?php   if (!empty($this->usersHasCreatingDates)): ?>
<table class="dates-serial-numbers">
    <tr>
        <th>UserName</th>
        <th>Email</th>
        <th>Serial numbers and dates</th>
    </tr>
    <?php

        foreach ($this->usersHasCreatingDates as $userId) {
            ?>
            <tr>
                <td><?php echo $userId->display_name ?></td>
                <td><?php echo $userId->user_email ?></td>
                <?php
                $datesCreateSerialNumbers = unserialize(get_user_meta($userId->ID, $namField, true));
                foreach ($datesCreateSerialNumbers as $serialNum => $date) {
                    $item = $serialNum . " - " . date("Y-m-d H:i:s", $date) . ";";
                    ?>
                    <td><?php echo $item ?></td><?php
                }
                ?>
            </tr>
            <?php
        }
    ?>
</table>
<?php else:?>
    <p><?php echo _e("Unfortunately. You don't have serial numbers",'nalperiron')?></p>
<?php endif;?>