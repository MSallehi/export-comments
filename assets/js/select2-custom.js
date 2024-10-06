jQuery(document).ready(function ($) {
  $("#post_id").select2({
    ajax: {
      url: ajaxurl,
      dataType: "json",
      delay: 250,
      data: function (params) {
        return {
          q: params.term,
          action: "search_posts",
        };
      },
      processResults: function (data) {
        return {
          results: data,
        };
      },
      cache: true,
    },
    minimumInputLength: 3,
    placeholder: "نام برگه یا نوشته خود را وارد نمایید",
  });
});
