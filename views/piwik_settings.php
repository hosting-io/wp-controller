<style type="text/css">
    .form label{width:200px;display: inline-block;}
</style>
<div class="wrap">
    <div id="icon-options-general" class="icon32"><br /></div>
    <h2>Analytics collection settings</h2>

    <form method="post" action="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php'; ?>" enctype="multipart/form-data">
<div class="form">
<table>
    <tr>
        <td><label for="">Analytics path:</label></td>
        <td><input name="piwik_hostname" type="text" id="piwik_hostname" value="<?php echo ($settings['piwik_hostname'])?$settings['piwik_hostname']:'stats.campaigns.io'; ?>" />
            <p class="description">stats.campaigns.io</p>
        </td>
    </tr>
    <tr>
        <td><label for="">Your Site ID:</label></td>
        <td><input name="piwik_siteid" type="text" id="piwik_siteid" value="<?php echo $settings['piwik_siteid']; ?>" />
            <p class="description">Site ID from campaigns app</p>
            <input type="hidden" name="action" value="save_piwik">
        </td>
    </tr>
    <tr>
        <td></td>
        <td><?php
    submit_button('Save', 'button button-primary button-large', 'submit', false);
?></td>
    </tr>

</table>


</div>
    </form>
