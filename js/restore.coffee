$(document).ready ->
    pull = (value) ->
        $("input[name="+value+"]").val()

    $("form[method=POST]").submit ->
        # Necessary caching because we mess with DOM
        params =
            contextid: pull "contextid"
            restore_to: pull "restore_to"
            filename: pull "filename"
            confirm: pull "confirm"

        loading = $("#restore_loading").html()
        $(".buttons").html loading

        $.post "restore.php", params, (data) ->
            $("#notice").html data

        false
