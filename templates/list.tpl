<div class="simple_restore_filter_form">
    <form method="POST">
        <div class = "simple_restore_filter_form_contents">
            <span class="simple_restore_filter_by">
                {"shortname:moodle"|s}
            </span>
            <select name="sn_filter">
                {foreach $options as $key => $value}
                    <option value="{$key}" {if $key == 'contains'} SELECTED {/if}>
                        {$value}
                    </option>
                {/foreach}
            </select>
            <input class="simple_restore_filter_sn" name="shortname"/>
            <input name="submit" class="simple_restore_submit_button" type="submit" value="{"submit:moodle"|s}"/>
        </div>
    </form>
</div>
