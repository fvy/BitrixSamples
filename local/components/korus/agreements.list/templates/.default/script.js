$(document).ready(function () {
  var table = $('.agreements-data-table').DataTable({
    dom: '<"top"<"row"<"col-auto mr-0"p><"col-auto ml-auto row">>><"data_tables_scroll"rt><"bottom"<"row"<"col-auto mr-0"p>>>',
    pageLength: 50,
    searching: true,
    info: false,
    ordering: false,
    pagingType: 'full_numbers',
    language: {
      url: "/local/assets/js/vendors/tables/datatable/lang/ru.json"
    },
    data: window.DATA_AGGREMENTS,
    initComplete: function () {
      hScroll(".data_tables_scroll");
      $("#agreements-overflow-hidden").removeClass("agreements-overflow-hidden invisible");
      $(".loader-wrapper").addClass("hidden");

      JsDataTableRender.fixTableHeader(true);
    },
    fnDrawCallback: function (oSettings) {
      if (oSettings._iDisplayLength > oSettings.fnRecordsDisplay()) {
        $(oSettings.nTableWrapper).find('.dataTables_paginate').hide();
      } else {
        $(oSettings.nTableWrapper).find('.dataTables_paginate').show();
      }
      
      JsDataTableRender.recalcWidth();
    }
  });

  $.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex, row, counter) {
      var text = $('#agreements_search_field').val();
      
      var clearText = row[1].replace(/<strong>|<\/strong>/gi, '');
      if(row[1] !== clearText) {
        table.cell(dataIndex, 1).data(clearText);
      }
      
      // Проверка текста
      if (text.length >= 2) {
        if (data[1].toUpperCase().indexOf(text.toUpperCase()) === -1) {
          return false;
        } else {
          // Подсветка
          var regexp = new RegExp('(' + text + ')', 'ig');
          var modText = clearText.replace(regexp, function ($1) {
            return '<strong>' + $1 + '</strong>';
          });
          table.cell(dataIndex, 1).data(modText);
        }
      }

      return true;
    }
  );

  // обработка формы по вводу текста в поиске
  var timerSearch;
  $(document).on("keyup", '#agreements_search_field', function() {
    window.clearTimeout(timerSearch);
    timerSearch = setTimeout(function () {table.draw();}, 700);
  });
                
  // Очистка поиска
  $(document).on("click", '#clear_agreements_search_field', function (e) {
    e.preventDefault();
    $('#agreements_search_field').val('');
    table.draw();
  });
  
  initDialog(".conf-modal-dialog", {
    buttons: [
      {
        text: "Нет",
        "class": "btn btn-round grey btn-outline-secondary col-5 pull-right",
        click: function () {
          $(this).dialog("close");
        }
      },
      {
        text: "Да",
        "class": "btn btn-round btn-info col-5 pull-right",
        click: function () {
          var jqXhr;
          if (jqXhr && jqXhr.readyState !== 4) {
            return;
          }

          var rawId = $(this).attr('data-raw-id');
          var oData = [
            {name: 'rawId', value: rawId},
            {name: 'action', value: "delete"}
          ];
          jqXhr = $.ajax({
            url: '/agreements/delete_raw_agreement_ajax.php',
            type: 'POST',
            data: oData,
            dataType: 'json',
            success: function (response) {
              var tr = $('#rawId-' + rawId).closest("tr");
              table.row(tr).remove().draw();
            },
            error: function (errorInfo) {
              console.error(errorInfo);
              return false;
            }
          });
          $(this).dialog("close");
        }
      }
    ]
  });

  $(document).on("click", ".conf-modal-dialog-btn", function () {
    $(".conf-modal-dialog").attr('data-raw-id', $(this).attr('data-raw-id')).dialog("open");
  });

  $(document).on("click", ".cancel-modal-dialog-btn", function () {
    JsAgreementsCancel.cancel($(this).data('id'));
  });
});

$(window).on('resize', function () {
  setTimeout(function () {
    JsDataTableRender.fixTableHeader(true);
  }, 500);
});
