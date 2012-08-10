M.block_simple_restore = {}

M.block_simple_restore.init = (Y) ->
    pull = (value) -> Y.one("input[name="+value+"]").get 'value'

    Y.one("form[method=POST]").on 'submit', (e) ->
        e.preventDefault()

        # Necessary caching because we mess with DOM
        params = {
            contextid: pull "contextid",
            restore_to: pull "restore_to",
            filename: pull "filename",
            confirm: pull "confirm",
        }

        Y.one(".buttons").getDOMNode().innerHTML =
            Y.one("#restore_loading").getDOMNode().innerHTML

        Y.io 'restore.php', {
            method: "POST",
            data: params,
            "on": {
                success: (id, result) ->
                    Y.one('#notice').getDOMNode().innerHTML = result.responseText
            }
        }

        false
