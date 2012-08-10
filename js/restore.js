(function(){
  M.block_simple_restore = {};
  M.block_simple_restore.init = function(Y) {
    var pull;
    pull = function(value) {
      return Y.one("input[name=" + value + "]").get('value');
    };
    return Y.one("form[method=POST]").on('submit', function(e) {
      var params;
      e.preventDefault();
      // Necessary caching because we mess with DOM
      params = {
        contextid: pull("contextid"),
        restore_to: pull("restore_to"),
        filename: pull("filename"),
        confirm: pull("confirm")
      };
      Y.one(".buttons").getDOMNode().innerHTML = Y.one("#restore_loading").getDOMNode().innerHTML;
      Y.io('restore.php', {
        method: "POST",
        data: params,
        "on": {
          success: function(id, result) {
            Y.one('#notice').getDOMNode().innerHTML = result.responseText;
            return Y.one('#notice').getDOMNode().innerHTML;
          }
        }
      });
      return false;
    });
  };
})();
