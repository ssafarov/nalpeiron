<div class="wrap">
    <h2></h2>

    <h2>Add License Code</h2>
    <style>
        .add_license_code table input,
        .add_license_code table select {
            width: 350px;
        }
    </style>
    <form class="add_license_code" method="post">
        <div style="color: red"><?= isset($error) ? $error : '' ?></div>
        <table>
            <tr>
                <th>
                    User email:
                <th>
                <td>
                    <input required="required" placeholder="Email" type="email" <?= $email ? 'readonly="readonly"' : '' ?> name="email" value="<?= $email ?>"/>
                </td>
            </tr>
            <tr>
                <th>
                    Nalpeiron profile name:
                <th>
                <td>
                    <?php
                    $product_ids = \MVC\Models\Licenses::instance()->getVirtualProductIDs();
                    $profiles = [];
                    foreach ($product_ids as $product_id) {
                        $product = get_product($product_id);
                        $profile = $product->get_attribute('nalpeiron_profilename');
                        if ($profile) {
                            $profiles[$profile] = $profile;
                        }
                    }

                    ?>
                    <select name="profile">
                        <?php foreach ($profiles as $item): ?>
                            <option <?= ($item == $profile) ? 'selected' : '' ?> ><?= $item ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>
                    License codes:
                <th>
                <td>
                    <input type="text" placeholder="Auto" name="license" value="<?= $license ?>"/>
                </td>
            </tr>
            <tr>
                <th>
                    Quantity:
                <th>
                <td>
                    <input type="number" placeholder="1" name="amount" value="<?= $amount ?>"/>
                </td>
            </tr>
            <tr>
                <th>
                    Modify date:
                <th>
                <td>
                    <input type="text" placeholder="+1 year" name="modify_data" value="<?= $modify_data ?>"/>
                </td>
            </tr>
        </table>
        <div>
            <input type="hidden" name="user_id" value="<?= $user_id ?>"/>
            <input id="update" class="button button-primary" type="submit" accesskey="s" value="Add code" name="add">
        </div>
    </form>
</div>