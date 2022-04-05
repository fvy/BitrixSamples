$(document).ready(function () {
  var jqXhr;
  var fileRequest;  
  var table = $('.additional-products').DataTable({
    "dom": '<"top"<"row"<"col-auto mr-0"p><"col-auto ml-auto row">>><"data_tables_scroll"rt><"bottom"<"row"<"col-auto mr-0"p>>>',
    pageLength: 50,
    searching: true,
    aaSorting: [],
    ordering: false,
    pagingType: 'full_numbers',
    language: {
      url: "/local/assets/js/vendors/tables/datatable/lang/ru_product.json"
    },
    data: window.dataTable,
    initComplete: function () {
      hScroll(".data_tables_scroll");

      table.columns([6, 7, 8]).visible(window.visibleSpecFields == 1);

      $("#gpnsm-dynamic-overflow").removeClass("gpnsm-dynamic-overflow invisible");
      $(".loader-wrapper").addClass("hidden");
      
      $(this).find('tr').each(function () {
        var tr = $(this);
        var product = tr.find('div.product');

        if (product.length > 0) {
          var code = product.data('code');
          var titleObj = $(".product-history-" + code);

          if (titleObj.length > 0) {
            tr.addClass('product-history').data('code', code);
          }
        }
      });
      
      $('tr.product-history').hover(function () {
        var code = $(this).data('code');
        var message = $('.product-history-' + code);
        var parentTr = $(this).closest('.data_tables_scroll');
        message.removeClass('hidden').css({
          position: 'relative', 
          top: (parseInt($(this).offset().top) - parseInt(message.offset().top) + $(this).height())+ 'px', 
          left: (parseInt(parentTr.offset().left) - parseInt(message.offset().left))+ 'px'
        });
      }, function () {
        var code = $(this).data('code');
        $('.product-history-' + code).addClass('hidden').removeAttr('style');
      });

      JsDataTableRender.fixTableHeader(true);
    },
    fnDrawCallback: function (oSettings) {
      var paginate = $(oSettings.nTableWrapper).find('.dataTables_paginate');

      if (oSettings._iDisplayLength > oSettings.fnRecordsDisplay()) {
        paginate.hide();
      } else {
        paginate.show();
      }
      
      JsDataTableRender.fixTableHeader(true);
    }
  });

  $.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex, row, counter) {
      var text = $('#search_field').val();

      var clearText = row[0].replace(/<strong>|<\/strong>/gi, '');
      if (row[0] !== clearText) {
        table.cell(dataIndex, 0).data(clearText);
      }

      // Проверка текста
      if (text.length >= 3) {
        if (data[0].toUpperCase().indexOf(text.toUpperCase()) === -1)
        {
          return false;
        } else {
          // Подсветка 
          var regexp = new RegExp('(' + text + ')', 'ig');
          var modText = clearText.replace(/<span(.*?)>([^]*?)<\/span>/ig, function ($1, attr, text) {
            var hlText = text.replace(regexp, function ($1, match) {
              return '<strong>' + match + '</strong>';
            });
            return "<span" + attr + ">" + hlText + "</span>";
          });
          table.cell(dataIndex, 0).data(modText);
        }
      }

      return true;
    }
  );

  // обработка формы по вводу текста в поиске
  var timerSearch;
  $(document).on("keyup", '#search_field', function () {
    window.clearTimeout(timerSearch);
    timerSearch = setTimeout(function () {
      table.draw();
    }, 700);
  });

  // Очистка поиска
  $(document).on("click", '#clear_search_field', function (e) {
    e.preventDefault();
    $('#search_field').val('');
    table.draw();
  });

  $("input#delivery_price, input#passing_prop, input#nonpallet").iCheck({
    checkboxClass: 'icheckbox_flat-blue'
  });

  // Запрос счета по товарам
  $(document).on('click', '.request_invoice_btn', function () {
    var additional = $('.additional-products').data('additional');
    var message = $("<div>").append($("<p>").html(BX.message('popupInfoPath').replace('#ADDITION_NUMBER#', $('.additional-products').data('additional-number'))));
    $('.gpnsm-request-loader').addClass('active');

    if (fileRequest) {
      $('.gpnsm-request-loader').removeClass('active');
      $('.conf-modal-request-invoice').html(message).dialog("open");
    } else {
      if (jqXhr && jqXhr.readyState !== 4) {
        return;
      }

      jqXhr = $.ajax({
        url: '/agreements/request_invoice.php',
        type: 'POST',
        data: {'additional': additional},
        dataType: 'json',
        success: function (response) {
          if (response.error) {
            $(".conf-modal-request-invoice").html(response.error);
          } else {
            $(".conf-modal-request-invoice").html(message);
            fileRequest = response;
          }
          $('.gpnsm-request-loader').removeClass('active');
          $('.conf-modal-request-invoice').dialog("open");
        },
        error: function (errorInfo) {
          console.error(errorInfo);
          return false;
        }
      });
    }
  });

  function downloadFile() {
    if (!fileRequest.file) {
      return false;
    }

    var bl = b64toBlob(fileRequest.file, fileRequest.mime);
    saveAs(bl, fileRequest.filename);
  }

  $(document).on('click', '.download_file', function (e) {
    e.preventDefault();
    downloadFile();
  });

  initDialog(".conf-modal-request-invoice", {
    width: 560,
    buttons: [
      {
        text: BX.message('close'),
        "class": "btn btn-round col-5 pull-right",
        click: function () {
          $(this).dialog("close");
        }
      },
      {
        text: BX.message('downloadInvoice'),
        "class": "btn btn-round btn-info col-5 pull-right",
        click: function () {
          downloadFile();
          $(this).dialog("close");
        }
      }
    ]
  });
  
  /**
   * Свернуть и развернуть историю
   */
  $(document).on('click', '.history-toggle', function () {
    var iel = $(this).find('i');
    var codeSort = $(".sort-history-selected").data('sort');
    if (iel.hasClass('ft-chevron-down')) {
      iel.removeClass('ft-chevron-down').addClass('ft-chevron-up');
      $(this).find('#history-toggle-text').text(BX.message('actionAllUp'));
      $(".additional-history-" + codeSort).removeClass("hidden");
    } else {
      iel.removeClass('ft-chevron-up').addClass('ft-chevron-down');
      $(this).find('#history-toggle-text').text(BX.message('actionAllDown'));
      $(".additional-history-" + codeSort).addClass("hidden");
    }
  });
  
  // Фильтр по истории
  $(document).on("click", '.sort-history-button', function (e) {
    e.preventDefault();

    if (!$(this).hasClass('sort-history-selected')) {
      $('.sort-history-button').removeClass('sort-history-selected');
      $(this).addClass('sort-history-selected');
      var sort = $(this).data('sort');
      
      if(sort == 'date' && !$(".additional-history-code").hasClass('hidden')) {
        $(".additional-history-date").removeClass("hidden");
        $(".additional-history-code").addClass("hidden");
      } 
      
      if(sort == 'code' && !$(".additional-history-date").hasClass('hidden')) {
        $(".additional-history-code").removeClass("hidden");
        $(".additional-history-date").addClass("hidden");
      }
    }
  });
});

$(window).on('resize', function () {
  setTimeout(function () {
    JsDataTableRender.fixTableHeader(true);
  }, 500);
});