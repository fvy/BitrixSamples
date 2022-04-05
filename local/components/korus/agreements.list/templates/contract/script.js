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
    data: window.dataTable,
    initComplete: function () {
      hScroll(".data_tables_scroll");
    },
    fnDrawCallback: function (oSettings) {
      var paginate = $(oSettings.nTableWrapper).find('.dataTables_paginate');
      if (oSettings._iDisplayLength > oSettings.fnRecordsDisplay()) {
        paginate.hide();
      } else {
        paginate.show();
      }
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
});
