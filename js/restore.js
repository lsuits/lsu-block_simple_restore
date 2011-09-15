(function() {
  $(document).ready(function() {
    var pull;
    pull = function(value) {
      return $("input[name=" + value + "]").val();
    };
    return $("form[method=POST]").submit(function() {
      var loading, params;
      params = {
        contextid: pull("contextid"),
        restore_to: pull("restore_to"),
        filename: pull("filename"),
        confirm: pull("confirm")
      };
      loading = $("#restore_loading").html();
      $(".buttons").html(loading);
      $.post("restore.php", params, function(data) {
        return $("#notice").html(data);
      });
      return false;
    });
  });
}).call(this);
