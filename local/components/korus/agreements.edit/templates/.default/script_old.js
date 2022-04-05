(function (Module, ContractSelect, $) {
  var form = $('#gpnsm_additional_raw'),
    ajaxUrl,
    afterSaveUrl,
    dataTableBlock = $('.js-data-table-block'),
    dataTable,
    ndsCoef = 1.2,
    fiftyCoef = 0.9,
    volumeDef = 1000000000,
    remains5050Access = 0,
    remains5050 = 0,
    additionId = 0,
    advert5050 = 0,
    measures,
    successUrl,
    priceTypes = [],
    orderProducts = [],
    additionProductsEdit = [],
    deletedList = [],
    localStorageKeyPageSize = 'AGREEMENTS_RAW',
    reserves,
    productsError = [],
    previous = {
      period: form.find('#period').val(),
      store: form.find('#store').val()
    },
    products = [],
    brands = [],
    priceAgreementsFilter = [],
    priceTypeFilter = [],
    storeType = "",
    errorEL = false,
    errorTR = true;

  function initInfoTooltips() {
    $('.icon-info').tooltip();
  }

  function getActiveProductValues() {
    return dataTable.$('.js-volume').filter(function () {
      var deleteCheckbox = $(this).closest('tr').find('input.js-to-delete:checkbox');

      return !deletedList[deleteCheckbox.val()];
    });
  }

  function hasSelectedProducts() {
    var hasNotEmptyValue = false;

    getActiveProductValues().each(function () {
      hasNotEmptyValue = hasNotEmptyValue || !!parseFloat(this.value);
    });

    return hasNotEmptyValue;
  }

  function extendContractChangeEvents() {
    var contractDialogApplyHandler = function () {
      var contractCallback = null,
        callbackArg = null;

      return {
        clickHandler: function () {
          if (contractCallback) {
            contractCallback(callbackArg);
          } else {
            $('#gpnsm_request_create').submit();
          }
          $(this).dialog("close");
        },
        setContractCallback: function (callback, arg) {
          contractCallback = callback;
          callbackArg = arg;
        }
      };
    }();
    
    var contractDialog = initDialog(".conf-modal-change-contract", {
      buttons: [
        {
          text: "Отмена",
          "class": "btn btn-round grey btn-outline-secondary col-5 pull-right",
          click: function () {
            $(this).dialog("close");
          }
        },
        {
          text: "Ок",
          "class": "btn btn-round btn-info col-5 pull-right",
          click: contractDialogApplyHandler.clickHandler
        }
      ]
    });

    ContractSelect.addCallback('requestProductsCheck', function () {
      if (!hasSelectedProducts()) {
        contractDialogApplyHandler.setContractCallback(null, null);
        return false;
      } else {
        return function (changeContractCallback, value) {
          contractDialogApplyHandler.setContractCallback(changeContractCallback, value);
          contractDialog.dialog("open");
        };
      }
    });
  }

  function updateStoreList() {
    $.ajax({
      url: ajaxUrl,
      method: 'post',
      data: {
        'action': 'getStores',
        'period': form.find('#period').val()
      },
      dataType: 'json',
      beforeSend: function () {
        preloaderOpen();
      },
      success: function (response) {
        var storeSelect = form.find('#store');
        storeSelect.empty();
        for (var index in response) {
          storeSelect.append(new Option(response[index], index, false, false));
        }
        storeSelect.val(storeSelect.find('option:first').val());

        reloadDataTable();
      },
      complete: function () {
        preloaderClose();
      }
    });
  }

  function reloadDataTable() {
    dataTable.ajax.reload();
    dataTable.draw('full-reset');
  }

  function initDialogs() {
    initDialog(".conf-modal-change-period", {
      buttons: [
        {
          text: "Отмена",
          "class": "btn btn-round grey btn-outline-secondary col-5 pull-right",
          click: function () {
            form.find('#period').val(previous.period).trigger('change.select2');
            $(this).dialog("close");
          }
        },
        {
          text: "Ок",
          "class": "btn btn-round btn-info col-5 pull-right",
          click: function () {
            updateStoreList();
            $(this).dialog("close");
          }
        }
      ]
    });

    initDialog(".conf-modal-change-store", {
      buttons: [
        {
          text: "Отмена",
          "class": "btn btn-round grey btn-outline-secondary col-5 pull-right",
          click: function () {
            form.find('#store').val(previous.store).trigger('change.select2');
            $(this).dialog("close");
          }
        },
        {
          text: "Ок",
          "class": "btn btn-round btn-info col-5 pull-right",
          click: function () {
            reloadDataTable();
            $(this).dialog("close");
          }
        }
      ]
    });

    initDialog(".conf-modal-advert", {
      buttons: [
        {
          text: "Ок",
          "class": "btn btn-round btn-info col-5 pull-right",
          click: function () {
            $(this).dialog("close");
          }
        }
      ]
    });
  }

  function updateVolumesTotal() {
    let sumMatchedPallet = 0;
    let sumMatchedCount = 0;
    let sumAllowedPallet = 0;
    let sumAllowedCount = 0;
    let sumContractPieces = 0;
    let sumContractCount = 0;
    
    dataTable.$('tr').each(function (index, value) {
      var tr = $(value);

      if (storeType === 'packing') {
        sumMatchedPallet = sumMatchedPallet + parseFloat(tr.find('.td-matched-pallet').html().replace(' ', ''));
        sumAllowedPallet = sumAllowedPallet + parseFloat(tr.find('.td-available-pallet').html().replace(' ', ''));
      }

      sumMatchedCount = sumMatchedCount + parseFloat(tr.find('.td-matched-count').html().replace(' ', ''));
      sumAllowedCount = sumAllowedCount + parseFloat(tr.find('.td-available-count').html().replace(' ', ''));

      tr.find('.js-volume').each(function (svIndex, svValue) {
        let volume = parseFloat($(svValue).val());
        sumContractCount = sumContractCount + volume;
      });

      tr.find('.js-pieces').each(function (svIndex, svValue) {
        let volume = parseFloat($(svValue).html().replace(' ', ''));
        sumContractPieces = sumContractPieces + volume;
      });
    });

    if (storeType === 'packing') {
      let signsCount = 0;
      let unit_pie = BX.message('UNIT_PIECES');
      let unit_pal = BX.message('UNIT_PALLET') + '.';

      $('.matched-pallet-total').html($.number(sumMatchedPallet, signsCount, '.', ' ') + ' ' + unit_pal);
      $('.allowed-pallet-total').html($.number(sumAllowedPallet, signsCount, '.', ' ') + ' ' + unit_pal);
      $('.volume-pallet-total').html($.number(sumContractCount, signsCount, '.', ' ') + ' ' + unit_pal);

      $('.matched-count-total').html($.number(sumMatchedCount, signsCount, '.', ' ') + ' ' + unit_pie);
      $('.allowed-count-total').html($.number(sumAllowedCount, signsCount, '.', ' ') + ' ' + unit_pie);
      $('.volume-count-total').html($.number(sumContractPieces, signsCount, '.', ' ') + ' ' + unit_pie);
    }

    if (storeType === 'pouring') {
      let signsCount = 3;
      let unit = BX.message('UNIT_TON');

      $('.matched-count-total').html($.number(sumMatchedCount, signsCount, '.', ' ') + ' ' + unit);
      $('.allowed-count-total').html($.number(sumAllowedCount, signsCount, '.', ' ') + ' ' + unit);
      $('.volume-count-total').html($.number(sumContractCount, signsCount, '.', ' ') + ' ' + unit);
    }
  }

  function updateTableTotals() {
    var totalWeight = 0;
    var totalCount = 0;
    var totalSum = 0;
    remains5050 = 0;

    dataTable.$("tr").each(function (index, value) {
      var tr = $(value);
      if (deletedList[tr.find('input.js-to-delete:checkbox').val()]) {
        return true;
      }

      tr.find('.js-div-source .js-volume').each(function (svIndex, svValue) {
        var input = $(this);
        var volume = parseFloat(input.val());
        var priceTypeId = $(tr.find(".js-div-source .price-type-select")[svIndex]).val();
        if (!priceTypeId || volume <= 0) return;
        
        var weight = parseFloat(input.data('weight'));
        var fiftyFifty = $(tr.find(".js-div-source .js-50-50")[svIndex]).prop("checked");
        var productId = input.data("id");
        var price = priceTypes[productId][priceTypeId]['PRICE'];
        
        totalCount++;
        if (input.data('pieces')) {
          volume *= input.data('pallet-rate');
        }
        totalWeight += weight ? (volume * weight / 1000) : volume;

        if (fiftyFifty) {
          remains5050 += price * volume * (1 - fiftyCoef);
          price *= fiftyCoef;
        }
        totalSum += price * volume;
      });
      
      tr.find('.js-target-div .js-volume').each(function (tvIndex, tvValue) {
        var input = $(this);
        var volume = parseFloat(input.val());
        var priceTypeId = $(tr.find(".js-target-div .price-type-select")[tvIndex]).val();
        if (!priceTypeId || volume <= 0) return;
        
        var weight = parseFloat(input.data('weight'));
        var fiftyFifty = $(tr.find(".js-target-div .js-50-50")[tvIndex]).prop("checked");
        var productId = input.data("id");
        var price = priceTypes[productId][priceTypeId]['PRICE'];
        
        totalCount++;
        if (input.data('pieces')) {
          volume *= input.data('pallet-rate');
        }
        totalWeight += weight ? (volume * weight / 1000) : volume;

        if (fiftyFifty) {
          remains5050 += price * volume * (1 - fiftyCoef);
          price *= fiftyCoef;
        }
        totalSum += price * volume;
      });
    });

    dataTableBlock.find('.price-total').text($.number(totalSum * ndsCoef, 2, '.', ' '));
    dataTableBlock.find('.js-table-totals-weight').text($.number(totalWeight.toFixed(3), 3, '.', ' '));
    dataTableBlock.find('.js-table-totals-count').text($.number(totalCount, 0, '.', ' '));
    dataTableBlock.find('.js-table-totals-position').text(declOfNum(totalCount, BX.message('positionArr')));

    disableButtonElm($('#save_btn'), (totalCount ? false : true), "btn-warning", "disabled");
    disableButtonElm($('#save_raw_btn'), (totalCount ? false : true), "btn-info", "disabled");
    
    updateVolumesTotal();
  }
  
  function initDataTableContent() {
    dataTable.$('input:checkbox').iCheck({checkboxClass: 'icheckbox_square-blue'});
    initSelect2('.price-type-select', {});

    dataTable.$('.decimal-inputmask').inputmask({'alias': 'decimal', 'radixPoint': '.'});
    var touchSpinOptions = {
      stepinterval: 1,
      maxboostedstep: 10000000,
      replacementval: 0
    };
    
    if (storeType === 'pouring') {
      $.extend(touchSpinOptions,{
        stepinterval: 100,
        step: 1,
        decimals: 3 
      });
    }

    dataTable.$('.touchspin-quantity').TouchSpin(touchSpinOptions);
  }
  
  function productVolumeChange(el, parentClass, index, keyCode) {
    var tr = el.closest('tr');
    var isPieces = el.data('pieces'),
      limit = el.data("limit"),
      productId = el.data("id"),
      currentVolume = 0,
      volume = Math.abs(el.val()),
      price = el.data("price"),
      fiftyFifty = el.data("fiftyFifty");
    
    if (typeof keyCode === 'undefined') {
      keyCode = '';
    }
    
    if (isPieces) {
      volume *= el.data('pallet-rate');

      $(tr.find(parentClass + " .js-pieces")[index]).text($.number(volume, 0, '.', ' '));
    }
    
    if (fiftyFifty) {
      price *= fiftyCoef;
    }

    $(tr.find(parentClass + " .price-piece-total")[index]).text($.number((price * volume), 2, '.', ' '));
    
    if (errorEL) {
      tr.find(".js-volume").each(function() {
        currentVolume += Math.abs($(this).val());
      });

      if (limit >= currentVolume) {
        productsError[productId] = '';
        tr.find(".js-volume").css('background', '#FFF');
      }
    }

    updateTableTotals();
  }
  
  $(document).on('change', '.js-div-source .price-type-select', function () {
    priceTypeSelectChange($(this), ".js-div-source", 0);
  });

  $(document).on('change', '.js-target-div .price-type-select', function () {
    priceTypeSelectChange($(this), ".js-target-div", $(this).closest('div.js-div-copy').index());
  });

  $(document).on('ifChecked ifUnchecked', '.js-div-source .js-50-50', function () {
    fiftyFiftyChange($(this), ".js-div-source", 0);
  });

  $(document).on('ifChecked ifUnchecked', '.js-target-div .js-50-50', function () {
    fiftyFiftyChange($(this), ".js-target-div", $(this).closest('div.js-div-copy').index());
  });

  $(document).on('ifChecked ifUnchecked', '.js-div-source .js-special-price', function () {
    specialPriceChange($(this), ".js-div-source", 0);
  });

  $(document).on('ifChecked ifUnchecked', '.js-target-div .js-special-price', function () {
    specialPriceChange($(this), ".js-target-div", $(this).closest('div.js-div-copy').index());
  });

  // фильтр по отмеченным позициям
  $(document).on('ifChecked ifUnchecked', '#search-not-empty', function () {
    dataTable.draw();
  });

  $(document).on('change keyup', '.js-div-source .js-volume', function (e) {
    var keyCode = '';
    if (typeof e.originalEvent !== 'undefined') {
      keyCode = e.originalEvent.keyCode;
    }
    productVolumeChange($(this), '.js-div-source', 0, keyCode);
  });

  $(document).on('change keyup', '.js-target-div .js-volume', function (e) {
    var keyCode = '';
    if (typeof e.originalEvent !== 'undefined') {
      keyCode = e.originalEvent.keyCode;
    }
    productVolumeChange($(this), ".js-target-div", $(this).closest('div.js-div-copy').index(), keyCode);
  });

  $(document).on('focus', '.js-volume', function () {
    if($(this).val() == 0) {
      $(this).val('');
    }
  });

  function paintProductsError() {
    for (let index in productsError) {
      if (productsError[index]) {
        $('.js-volume[data-id = ' + productsError[index] + ']').css('background', '#FF8898');
      }
    }
  }
  
  function priceTypeSelectChange(el, parentClass, index) {
    var tr = el.closest("tr");
    var productField = tr.find(parentClass + " input.js-volume")[index];
    var productId = $(productField).data("id");

    var priceTypeId = el.val();
    var priceType = priceTypes[productId][priceTypeId];
    
    var price = (priceTypeId && priceType['PRICE']) ? priceType['PRICE'] : 0;
    var advert = $(tr.find(parentClass + " .js-50-50")[index]).prop("checked");
    var specialPrice = $(tr.find(parentClass + " .js-special-price")[index]).prop("checked");
    var maxValue = 0;
    var minValue = 0;
    
    if (priceTypeId) {
      maxValue = (priceType['VOLUME'] !== undefined && priceType['VOLUME'] > 0) ? priceType['VOLUME'] : volumeDef;
      minValue = getMinVolume(productId, priceTypeId, advert ? 1 : 0, specialPrice ? 1 : 0);
    }

    var palletRate = parseInt(products[productId]['PALLET_RATE']);
    
    if (!priceTypeId || priceType['TYPE'] == "individual" || advert5050) {
      if (advert5050) {
        $(tr.find(parentClass + " .js-special-price")[index]).prop("disabled", true).prop("checked", false).iCheck('update');
      } else {
        $(tr.find(parentClass + " .js-50-50")[index]).prop("disabled", true).prop("checked", false).iCheck('update');
        $(tr.find(parentClass + " .js-special-price")[index]).prop("disabled", true).prop("checked", false).iCheck('update');
      }
    } else {
      $(tr.find(parentClass + " .js-50-50")[index]).prop("disabled", false).iCheck('update');
      $(tr.find(parentClass + " .js-special-price")[index]).prop("disabled", false).iCheck('update');
    }
    
    price *= advert == 1 ? fiftyCoef : 1;

    var maxValueNew = storeType == 'packing' ? parseInt(maxValue / palletRate) : parseFloat(maxValue).toFixed(3);
    $(productField).closest(".js-div-source").find(".max-count").text(maxValue == volumeDef ? "∞" : maxValueNew);
    
    $(productField).trigger("touchspin.updatesettings", {max: maxValueNew});
    $(productField).trigger("touchspin.updatesettings", {min: minValue});
    $(productField).data({"max": maxValueNew});
    $(productField).data({"price-type-id": priceTypeId});
    $(productField).data({"price": price * ndsCoef});
    $(tr.find(parentClass + " .price-piece")[index]).text($.number(price, 2, '.', ' '));
    $(tr.find(parentClass + " .price-piece-nds")[index]).text($.number((price * ndsCoef), 2, '.', ' '));
    $(tr.find(parentClass + " .price-piece-total")[index]).text($.number(0, 2, '.', ' '));
    
    $(tr).find("input.js-volume").each(function(i, elem) {
      $(elem).trigger('change');
    });
  }
  
  /**
   * Чекбоксик 50/50
   * @param {type} el
   * @param {type} parentClass
   * @param {type} index
   * @returns {undefined}
   */
  function fiftyFiftyChange(el, parentClass, index) {
    var tr = el.closest("tr");
    $(tr.find(parentClass + " .js-special-price")[index]).prop("checked", false).iCheck('update');
    changePriceField(el, parentClass, index);    
    $(tr.find(parentClass + " input.js-volume")[index]).trigger('change');
  }
  
  /**
   * Чекбоксик Специальная цена
   * @param {type} el
   * @param {type} parentClass
   * @param {type} index
   * @returns {undefined}
   */
  function specialPriceChange(el, parentClass, index) {
    $(el.closest("tr").find(parentClass + " .js-50-50")[index]).prop("checked", false).iCheck('update');
    changePriceField(el, parentClass, index);
    $(el.closest("tr").find(parentClass + " input.js-volume")[index]).trigger('change');
  }
  
  function changePriceField(el, parentClass, index) {
    var tr = el.closest("tr");
    var productField = tr.find(parentClass + " input.js-volume")[index];
    var productId = $(productField).data("id");
    var priceTypeId = $(tr.find(parentClass + " .price-type-select")[index]).val();
    var fiftyFifty = $(el.closest("tr").find(parentClass + " .js-50-50")[index]).prop("checked");
    var specialPrice = $(el.closest("tr").find(parentClass + " .js-special-price")[index]).prop("checked");

    var minValue = getMinVolume(productId, priceTypeId, fiftyFifty ? 1 : 0, specialPrice ? 1 : 0);
    
    $(productField).data({"fifty-fifty": fiftyFifty});

    var priceType = priceTypes[productId][priceTypeId];
    var price = priceType['PRICE'] ? priceType['PRICE'] : 0;

    if (fiftyFifty) {
      price *= fiftyCoef;
    }

    $(productField).trigger("touchspin.updatesettings", {min: minValue});
    $(tr.find(parentClass + " .price-piece")[index]).text($.number(price, 2, '.', ' '));
    $(tr.find(parentClass + " .price-piece-nds")[index]).text($.number((price * ndsCoef), 2, '.', ' '));
  }
  
  function getMinVolume(productId, priceType, advert, specialPrice) {
    var minVolume = 0;
    if (orderProducts[productId]) {
      $.each(orderProducts[productId], function(index, value) {
        if (value['PRICE_TYPE'] == priceType
          && value['ADVERT_50_50'] == advert
          && value['SPECIAL_PRICE'] == specialPrice) {

          minVolume += parseFloat(value['VOLUME']);
        }
      });
    }
    return minVolume;
  }

  function initDataTable() {    
    function updateDeleted() {
      var select = dataTableBlock.find('#revert-deleted-row');

      select.empty().append(new Option('', '', true, true));

      deletedList = [];
      dataTable.$('input.js-to-delete:checkbox:checked').each(function(i, obj) {
        deletedList[obj.value] = [obj.value];
        select.append(new Option(products[obj.value].NAME, obj.value, false, false));
      });

      dataTable.draw();
    }

    function getPriceTypeSelect(productIndex, defaultOption, defProduct) {
      var select = $('<select>', {class: "price-type-select", "data-placeholder": "Выберите прайс"});
      
      if (!defaultOption) {
        select.append($("<option>"));
      }

      if (priceTypes[productIndex]) {
        $.each(priceTypes[productIndex], function (index, value) {
          var option = getPriceTypeOption(value);

          if ((defaultOption && value["DEFAULT"] == 1)
            || defProduct["PRICE_TYPE"] == value["PRICE_TYPE_1C_ID"]) {
            option.attr({"selected": "selected"});
            defaultOption = false;
          }
          select.append(option);
        });
      }      

      return select;
    }
    
    function getPriceTypeOption(value) {
      return $("<option>", {
        "value": value["PRICE_TYPE_1C_ID"], 
        "text": value["PRICE_TYPE_NAME"], 
        "data-type": value["TYPE"], 
        "data-agreement-id": value["AGREEMENT_1C_ID"]
      });
    }

    function getCheckbox(productIndex, name, value, cssClass, checked, disabled) {
      var input = $('<input>', {
        type: 'checkbox',
        class: 'form-control' + (cssClass ? ' ' + cssClass : ''),
        name: name ? 'product[' + productIndex + '][' + name + '][]' : '',
        value: value
      });

      if(checked && checked > 0) {
        input.attr({"checked": "checked"});
      }

      if(disabled && disabled > 0) {
        input.attr({"disabled": "disabled"});
      }
      
      return $('<fieldset>', {class: 'custom-checkbox'}).append(input);
    }

    function getHiddenInput(productIndex, name, value) {
      return $('<input>', {type: 'hidden', name: 'product[' + productIndex + '][' + name + ']', value: value});
    }

    function getVolumeInput(productIndex, name, productInfo, isPieces, max, maxVolume, defProduct) {        
        var minVolume = 0;
        if (orderProducts[defProduct['ID']]) {
          minVolume = getMinVolume(defProduct['ID'], defProduct['PRICE_TYPE'], defProduct['ADVERT_50_50'], defProduct['SPECIAL_PRICE']);
        }

      return $('<input>', {
        type: 'text',
        class: 'touchspin-quantity decimal-inputmask form-control js-volume ' + (defProduct['ID']? "raw" : ""),
        value: defProduct['VOLUME'] !== undefined ? defProduct['VOLUME'] : 0,
        name: 'product[' + productIndex + '][' + name + '][]',
        'data-limit': max > 0 ? max : 0,
        'data-max': maxVolume,
        'data-min': minVolume,
        'data-fifty-fifty': advert5050,
        'data-id': productIndex,
        'data-pieces': isPieces,
        'data-weight': productInfo.WEIGHT,
        'data-pallet-rate': productInfo.PALLET_RATE,
        'data-bts-button-down-class': 'btn bootstrap-touchspin-down btn-light',
        'data-bts-button-up-class': 'btn bootstrap-touchspin-up btn-light',
        'data-price-type-id': defProduct['PRICE_TYPE'] ? defProduct['PRICE_TYPE'] : "",
        'data-price': getDefaultPrice(defProduct) * ndsCoef
      });
    }

    function getVolumeBlock(productIndex, name, productInfo, isPieces, maxLimit, measureTitle, defProduct, maxVolume) {
      var palletRate = parseInt(products[productInfo['CODE']]['PALLET_RATE']);
      maxVolume = maxVolume > 0 ? maxVolume : (defProduct['ID'] ? volumeDef : 0);
      var maxVolumeNew = storeType == 'packing' ? parseInt(maxVolume / palletRate) : parseFloat(maxVolume).toFixed(3);
      var symbol = maxVolume == volumeDef ? "∞" : maxVolumeNew;

      return $('<div>', {class: 'align-items-center', style: 'display: flex'})
        .append(
          $('<div>', {class: "input-group bootstrap-touchspin",  style: "width:150px"})
            .append(getVolumeInput(productIndex, name, productInfo, isPieces, maxLimit, maxVolumeNew, defProduct))
        )
        .append($('<span>', {style: "padding-left: 10px", html: measureTitle}))
        .append($('<span>', {style: "padding-left: 10px;width: 85px;text-align: left;", html: "max - "}).append($('<span>', {class: "max-count", html: symbol})));
    }

    function getProductInfoHtml(product) {
      var additionHtml = BX.message('PRODUCT_NUMBER') + ': ' + product.CODE;

      if (product.CAPACITY && measures[product.MEASURE].SYMBOL !== 'т') {
        additionHtml += ' ' + BX.message('PRODUCT_CAPACITY') + ': ' +
          product.CAPACITY + BX.message('PRODUCT_CAPACITY_UNIT');
      }

      return $('<div>')
        .append($('<span>', {html: product.NAME}))
        .append($('<span>', {class: 'product_info', html: additionHtml}))
        .append(getHiddenInput(product.CODE, 'measure', product.MEASURE))[0].outerHTML;
    }

    initSelect2('#filter-brand', {});
    initSelect2('#filter-price-agreements, #filter-price-type', {});
    initSelect2('#revert-deleted-row', {minimumResultsForSearch: 1});
    dataTableBlock.find('#revert-deleted-row').on('change', function () {
      dataTable.$('#product-' + this.value + '-delete').prop('checked', false);
      updateDeleted();
    });

    var pageSize = parseInt(JSStorage.get(localStorageKeyPageSize));
    var pageLength = (pageSize) ? pageSize : 20;

    dataTable = dataTableBlock.find('.zero-configuration').DataTable({
      "dom": '<"top"<"row"<"col-auto mr-1"p><"col-auto mr-0 button-all-values"><"col-auto ml-auto row"<"col-auto page-len">>>><"data_tables_scroll"rt><"bottom"<"row"<"col-auto mr-0"p>>>',
      pageLength: pageLength,
      pagingType: 'full_numbers',
      searching: true,
      aaSorting: [],
      ordering: false,
      fixedHeader: {
        header: false,
        footer: false
      },
      language: {
        url: "/local/assets/js/vendors/tables/datatable/lang/ru.json"
      },
      ajax: {
        url: ajaxUrl,
        type: 'POST',
        data: function (data) {
          return $.extend(
            {}, data, {
              action: 'getProducts',
              additionId: additionId
            }
          );
        }
      },
      initComplete: function() {
        $(".page-len").append("Выводить по: ").css({"margin": "20px"})
          .append($("<a>", {href: '#', class: 'page-len-button ml-1', 'data-size': 20, text: 20}))
          .append($("<a>", {href: '#', class: 'page-len-button ml-1', 'data-size': 50, text: 50}))
          .append($("<a>", {href: '#', class: 'page-len-button ml-1', 'data-size': 100, text: 100}));
        $(".page-len").find('*[data-size='+pageLength+']').addClass('page-len-selected');
        
        $(".button-all-values")
          .append($("<button>", {"id": "all-values", "type": "button", "class": "btn btn-round btn-warning-gpnsm btn-info", "text": BX.message('BTN_ALL_VOLUES')}));
      },
      fnDrawCallback: function (oSettings) {
        dataTable.off('change', 'input');
        
        var paginate = $(oSettings.nTableWrapper).find('.dataTables_paginate');
        
        if (oSettings._iDisplayLength >= oSettings.fnRecordsDisplay()) {
          paginate.parent().addClass("hidden");
        } else {
          paginate.parent().removeClass("hidden");
        }
                
        // Для наливки и паллет разные классы(ячейки скрываются)
        var changeClass8 = dataTable.$('.js-td-copy-8');
        if (storeType !== 'packing') {
          $(changeClass8).addClass('js-td-copy-volume').removeClass('js-td-copy-pieces');
        } else {
          $(changeClass8).addClass('js-td-copy-pieces').removeClass('js-td-copy-volume');
        }

        // пересборка селектора видов 
        rebuildSelectPriceTypes();
        initDataTableContent();
        updateTableTotals();
        
        $('.js-volume.raw').trigger('change');
        $('.icon-info-select').tooltip();

        dataTable.on('change', 'input', function () {
          if (errorTR) {
            let el = $(this).parents('[role="row"]').find('.js-volume');
            productsError[el.data('id')] = '';
            $('.js-volume[data-id = ' + el.data('id') + ']').css('background', '#FFF');
          }
        });

        paintProductsError();
      },

      columnDefs: [
        {'visible': false, 'targets': [9]},
        {className: 'text-center td-matched', 'targets': [3, 4]},
        {className: 'text-center td-allowed', 'targets': [5, 6]},
        {className: 'text-center', 'targets': [0, 2, 9]},
        {className: 'text-center js-td-copy js-td-copy-volume', 'targets': [7]},
        {className: 'text-center js-td-copy js-td-copy-8', 'targets': [8]},
        {className: 'text-center js-td-copy js-td-copy-price-type', 'targets': [10]},
        {className: 'text-center js-td-copy js-td-copy-50-50', 'targets': [11]},
        {className: 'text-center js-td-copy js-td-copy-special-price', 'targets': [12]},
        {className: 'text-center js-td-copy js-td-copy-price', 'targets': [13]},
        {className: 'text-center js-td-copy js-td-copy-price-nds', 'targets': [14]},
        {className: 'text-center js-td-copy js-td-copy-price-total', 'targets': [15]},
        {
          targets: 0,
          data: 'product',
          render: function ( data, type, row ) {
            return getCheckbox(data, 'delete', data, 'js-to-delete')
              .find('input:checkbox').prop('id', 'product-' + data + '-delete').end()
              [0].outerHTML;
          }
        },
        {
          targets: 1,
          data: 'product',
          render: function (data, type, row) {
            return getProductInfoHtml(products[data]);
          }
        },
        {
          targets: 2,
          data: 'product',
          render: function (data, type, row) {
            return brands[products[data].BRAND];
          }
        },
        {
          targets: 3,
          data: 'matched',
          render: function (data, type, row) {
            return '<span class="td-matched-pallet">' + data + '</span> ' + BX.message('UNIT_PALLET') + '.';
          }
        },
        {
          targets: 4,
          data: 'matched',
          render: function (data, type, row) {
            var count = data;
            if (storeType === 'packing') {
              var product = products[row.product];
              count *= parseFloat(product.PALLET_RATE);
            }
            if (storeType === 'pouring') {
              count = $.number(count, 3, '.', ' ')
            }
            return '<span class="td-matched-count">' + count + '</span> ' + ' ' + measures[products[row.product].MEASURE].SYMBOL;
          }
        },
        {
          targets: 5,
          data: 'available',
          render: function (data, type, row) {
            if (data < 0) {
                data = 0;
            }
            return '<span class="td-available-pallet">' + data + '</span> ' + BX.message('UNIT_PALLET') + '.';
          }
        },
        {
          targets: 6,
          data: 'available',
          render: function (data, type, row) {
            if (data < 0) {
                data = 0;
            }
            var count = data;
            if (storeType === 'packing') {
              var product = products[row.product];
              count *= parseFloat(product.PALLET_RATE);
            }
            if (storeType === 'pouring') {
              count = $.number(count, 3, '.', ' ')
            }

            return '<span class="td-available-count">' + count + '</span> ' + measures[products[row.product].MEASURE].SYMBOL;
          }
        },
        {
          targets: 7,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            var max = 0;
            if (priceTypes[data]) {
              var keys = Object.keys(priceTypes[data]);
              if (keys.length < 2) {
                var priceType = priceTypes[data][keys[0]];
                max = (priceType['VOLUME'] !== undefined && priceType['VOLUME'] > 0) ? priceType['VOLUME'] : volumeDef;
              } else if (additionProductsEdit[data]) {
                var priceType = priceTypes[data][additionProductsEdit[data].PRICE_TYPE];
                max = (priceType['VOLUME'] !== undefined && priceType['VOLUME'] > 0) ? priceType['VOLUME'] : volumeDef;
              }
            }

            if (additionProductsEdit[data]) {
                divSource = storeType === 'packing'
                    ? getVolumeBlock(data, 'volume', products[data], 1, row.available, BX.message('UNIT_PALLET'), additionProductsEdit[data], max)[0].outerHTML
                    : '';
            } else {
              divSource = storeType === 'packing'
              ? getVolumeBlock(data, 'volume', products[data], 1, row.available, BX.message('UNIT_PALLET'), [], max)[0].outerHTML
              : '';
            }
                          
            return '<div class="js-div-source">' + divSource + '</div><div class="js-target-div"></div>';
          }
        },
        {
          targets: 8,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            var max = 0;
            if (priceTypes[data]) {
              var keys = Object.keys(priceTypes[data]);
              
              if (keys.length < 2) {
                var priceType = priceTypes[data][keys[0]];
                max = (priceType['VOLUME'] !== undefined && priceType['VOLUME'] > 0) ? priceType['VOLUME'] : volumeDef;
              }
            }
            
            if (additionProductsEdit[data]) {
              divSource = storeType !== 'packing'
                ? getVolumeBlock(data,'volume',products[data],row.available,row.available, measures[products[row.product].MEASURE].SYMBOL, additionProductsEdit[data], max)[0].outerHTML
                : $('<span>', {class: 'js-pieces', html: 0})[0].outerHTML + ' ' + measures[products[row.product].MEASURE].SYMBOL;
            } else {
              divSource = storeType !== 'packing'
                ? getVolumeBlock(data,'volume',products[data],0,row.available, measures[products[row.product].MEASURE].SYMBOL, [], max)[0].outerHTML
                : $('<span>', {class: 'js-pieces', html: 0})[0].outerHTML + ' ' + measures[products[row.product].MEASURE].SYMBOL;
            }
                    
            return '<div class="js-div-source"><p>' + divSource + '</p></div><div class="js-target-div"></div>';
          }
        },
        {
          targets: 9,
          data: null,
          render: function (data, type, row) {
            return null;
          }
        },
        {
          targets: 10,
          data: 'product',
          render: function (data, type, row, meta) {
            var divSource = "";
            var keys = Object.keys(priceTypes[data]);

            if (additionProductsEdit[data]) {
                var select = getPriceTypeSelect(data, false, additionProductsEdit[data]);
                var divInfo = "";
                if (orderProducts[data]) {
                  select.prop("disabled", true);
                  divInfo = '<i class="icon-info icon-info-select" data-toggle="tooltip" data-placement="right" data-original-title="Изменение прайса невозможно, так как товар уже есть в разнарядках"></i>'
                }

                divSource = '<div class="js-div-source" style="float:left">' + select[0].outerHTML + '</div>' + divInfo;
            } else {
              divSource = '<div class="js-div-source">' + getPriceTypeSelect(data, keys.length < 2, [])[0].outerHTML + '</div>';
            }
            
            return divSource + '<div class="js-target-div"></div>';
          }
        },
        {
          targets: 11,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            var keys = Object.keys(priceTypes[data]);

            if (additionProductsEdit[data]) {
              divSource = getCheckbox(data, '50-50', 1, 'js-50-50', additionProductsEdit[data]['ADVERT_50_50'], !!orderProducts[data])[0].outerHTML;
            } else {
              divSource = getCheckbox(data, '50-50', 1, 'js-50-50', advert5050, true)[0].outerHTML;
            }
            
            return '<div class="js-div-source">' + divSource + '</div><div class="js-target-div"></div>';
          }
        },
        {
          targets: 12,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            var keys = Object.keys(priceTypes[data]);
            
            if (additionProductsEdit[data]) {
              var disabled = (priceTypes[data] 
                && priceTypes[data][additionProductsEdit[data]['PRICE_TYPE']] 
                && priceTypes[data][additionProductsEdit[data]['PRICE_TYPE']]['TYPE'] == "individual");
              disabled = disabled ? disabled : advert5050;
              
              if (orderProducts[data]) {
                disabled = true;
              }
              
              divSource = getCheckbox(data, 'specialPrice', 1, 'js-special-price', additionProductsEdit[data]['SPECIAL_PRICE'], disabled)[0].outerHTML;
            } else {
              divSource = getCheckbox(data, 'specialPrice', 1, 'js-special-price', 0, keys.length > 1)[0].outerHTML;
            }
            
            return '<div class="js-div-source">' + divSource + '</div><div class="js-target-div"></div>';
          }
        },
        {
          targets: 13,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            
            if (additionProductsEdit[data]) {
              divSource = "<span class='price-piece'>" + $.number(getDefaultPrice(additionProductsEdit[data]), 2, '.', ' ') + "</span> руб.";
            } else {
              divSource = "<span class='price-piece'>0.00</span> руб.";
            }
            
            return '<div class="js-div-source price-fixed">' + divSource + '</div><div class="js-target-div"></div>';
          }
        },
        {
          targets: 14,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            
            if (additionProductsEdit[data]) {
              divSource = "<span class='price-piece-nds'>" + $.number((getDefaultPrice(additionProductsEdit[data]) * ndsCoef), 2, '.', ' ') + "</span> руб.";
            } else {
              divSource = "<span class='price-piece-nds'>0.00</span> руб.";
            }
            
            return '<div class="js-div-source price-fixed">' + divSource + '</div><div class="js-target-div"></div>';
          }
        },
        {
          targets: 15,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            
            if (additionProductsEdit[data]) {
              divSource = "<span class='price-piece-total'>" + $.number((getDefaultPrice(additionProductsEdit[data]) * ndsCoef * additionProductsEdit[data]["VOLUME"]), 2, '.', ' ') + "</span> руб.";
            } else {
              divSource = "<span class='price-piece-total'>0.00</span> руб.";
            }
            
            return '<div class="js-div-source price-fixed">' + divSource + '</div><div class="js-target-div"></div>';
          }
        }
      ]
    });
    
    dataTable
      .on('xhr.dt', function (e, settings, json, xhr) {
        if (!json) return;
        products = json.products;
        brands = json.brands;
        storeType = json.storeType;
        priceTypes = json.priceTypes;
        priceAgreementsFilter = json.priceAgreementsFilter;
        priceTypeFilter = json.priceTypeFilter;

        dataTable.columns([3, 5, 7]).visible(storeType === 'packing', false);
        $('.store-unit').html((storeType == 'packing') ? BX.message('TH_UNIT') : BX.message('TH_UNIT_TON'));
        
        reloadFilterBrands();
        reloadFilterPriceAgreements(priceAgreementsFilter);
        reloadFilterPriceType(priceTypeFilter);
      });
      
    function getDefaultPrice(defProduct) {
        var price = 0;
        if (priceTypes[defProduct['ID']]) {
            price = (defProduct['PRICE_TYPE'] 
              && priceTypes[defProduct['ID']][defProduct['PRICE_TYPE']]
              && priceTypes[defProduct['ID']][defProduct['PRICE_TYPE']]['PRICE'])
            ? priceTypes[defProduct['ID']][defProduct['PRICE_TYPE']]['PRICE'] : price;
        }
        
        if (advert5050 == 1) {
          price *= fiftyCoef;
        }
        
        return price;
    }

    function reloadFilterBrands() {
      var brandFilterSelect = dataTableBlock.find('#filter-brand');
      brandFilterSelect.empty();
      brandFilterSelect.append(new Option(BX.message('FILTER_BRAND_NULL_VALUE'), '', true, false));
      
      for (var index in brands) {
        brandFilterSelect.append(new Option(brands[index], index, false, false));
      }
      
      brandFilterSelect.val(brandFilterSelect.find('option:first').val());
    }
    
    function reloadFilterPriceAgreements(priceAgreementsFilterEl, defaultOption) {
      var priceAgreementsFilterSelect = dataTableBlock.find('#filter-price-agreements');
      priceAgreementsFilterSelect.html('').append($('<option>', {value: '', text: BX.message('FILTER_PRICE_AGREEMENT_NULL_VALUE')}));

      $.each(priceAgreementsFilterEl, function (index, value) {
        priceAgreementsFilterSelect
          .append($('<option>', {
            value: value.UF_1C_ID, 
            text: value.UF_NAME, 
            'data-price-type': value.PRICE_TYPE_1C_ID
          })
        );
      });
    }

    function reloadFilterPriceType(priceTypeFilterEl, defSelected, defValue) {
      var priceTypeFilterSelect = dataTableBlock.find('#filter-price-type');
      priceTypeFilterSelect.html('');
      
      if (!defSelected) {
        priceTypeFilterSelect.append($('<option>', {value: '', text: BX.message('FILTER_PRICE_TYPE_NULL_VALUE')}));
      }

      $.each(priceTypeFilterEl, function (index, value) {
        var option = $('<option>', {
          value: value.PRICE_TYPE_1C_ID, 
          text: value.PRICE_TYPE_NAME,
          'data-price-agreement': value.UF_1C_ID
        });
        
        if (defSelected || (defValue && defValue == value.PRICE_TYPE_1C_ID)) {
          option.attr({selected: "selected"});
        }
        
        priceTypeFilterSelect.append(option);
      });
    }
    
    $.fn.dataTable.ext.search.push(
      function (settings, data, dataIndex, row, counter) {
        if (deletedList[row.product]) {
          return false;
        }

        var textFilter = dataTableBlock.find('#search_field').val();
        var brandFilter = dataTableBlock.find('#filter-brand').val();
        var isSearchNotEmpty = $('#search-not-empty').prop("checked");
        
        // Фильтр по Соглашению и Видам цен
        var priceAgreementId = dataTableBlock.find('#filter-price-agreements').val();
        var priceTypeId = dataTableBlock.find('#filter-price-type').val();

        if (priceAgreementId) {
          if (!priceTypes[row['product']]
            || (priceTypeId && (priceTypes[row['product']][priceTypeId] == undefined))
            || (priceTypeId && (priceTypes[row['product']][priceTypeId]['AGREEMENT_1C_ID'] != priceAgreementId))) {
            return false;
          }
          
          if (!priceTypeId) {
            var res = false;
            $.each(priceTypes[row['product']], function(index, value) {
              if (value['AGREEMENT_1C_ID'] == priceAgreementId) {
                res = true;
                return false;
              }
            });
            
            if (!res) return false;
          }
        }
        
        // Проверка текста
        if (textFilter.length >= 3) {
          var product = products[row['product']];
          if(product.NAME.toUpperCase().indexOf(textFilter.toUpperCase()) === -1
            && product.CODE.toUpperCase().indexOf(textFilter.toUpperCase()) === -1
          ) {
            return false;
          }
        }

        var volumeInput = dataTable.cell(dataIndex, 0).nodes().to$().closest('tr').find('.js-volume');

        // Проверка брендов
        if (brandFilter && brandFilter != products[row.product].BRAND
          || (isSearchNotEmpty === true && volumeInput.val() == 0)
        ) {
          return false;
        }

        return true;
      }
    );
  
    function rebuildSelectPriceTypes() {
      var priceAgreementId = dataTableBlock.find('#filter-price-agreements').val();
      var priceTypeId = dataTableBlock.find('#filter-price-type').val();

      dataTable.$(".price-type-select").each(function(index, volume) {
        var priceTypeSelect = $(this);
        if (priceTypeSelect.prop("disabled")) return;
        var priceTypeSelectVal = priceTypeSelect.val();
        var tr = priceTypeSelect.closest("tr");
        var productId = tr.find(".js-volume").data("id");
        var selectId;
        var options = [];
        
        $.each(priceTypes[productId], function (index, value) {
          if ((priceAgreementId && priceAgreementId != value["AGREEMENT_1C_ID"])
            || (priceTypeId && priceTypeId != value["PRICE_TYPE_1C_ID"])) {
            return;
          }
          
          var option = getPriceTypeOption(value);
          if ((priceTypeId && priceTypeId == value["PRICE_TYPE_1C_ID"])
            || (!priceAgreementId && priceTypeSelectVal == value["PRICE_TYPE_1C_ID"])) {
            option.prop("selected", true);
            selectId = value["PRICE_TYPE_1C_ID"];
          }
          options.push(option);

        });
        
        if (options.length == 1) {
          options[0].prop("selected", true);
          selectId = options[0].val();
        }
        
        if (options.length == 0) {
          return;
        }
        
        priceTypeSelect.html('').append($("<option>")).append(options).trigger("change");
        
        var maxValue = 0;
    
        if (selectId && priceTypes[productId] && priceTypes[productId][selectId]) {
          var priceTypeVolume = priceTypes[productId][selectId]['VOLUME'];
          maxValue = (priceTypeVolume !== undefined && priceTypeVolume > 0) ? priceTypeVolume : volumeDef;
          maxValue = storeType == 'packing' ? parseInt(maxValue / products[productId]['PALLET_RATE']) : parseFloat(maxValue).toFixed(3);
        }
        
        if (priceTypeSelect.closest('div').hasClass("js-div-source")) {
          $(tr.find(".js-div-source .js-volume")[priceTypeSelect.closest('div').index()]).data("max", maxValue);
        } else {
          $(tr.find(".js-target-div .js-volume")[priceTypeSelect.closest('div').index()]).data("max", maxValue);
        }
      });
    }
    
    dataTableBlock.find('.js-remove-rows').on('click', updateDeleted);
    // Фильтр по брендам
    $(document).on("change", '#filter-brand', dataTable.draw);

    // Фильтр по прайсам
    $(document).on("change", '#filter-price-agreements', function () {
      var agreementId = $(this).val();
      var priceTypeId = $('#filter-price-type').val();
      var newPriceTypeFilter = [];
      
      if (!agreementId) {
        reloadFilterPriceType(priceTypeFilter);
      } else {
        $.each(priceTypeFilter, function(index, value) {
          if (value['UF_1C_ID'] == agreementId) {
            newPriceTypeFilter.push(value);
          }
        });

        reloadFilterPriceType(newPriceTypeFilter, newPriceTypeFilter.length == 1, priceTypeId);
      }
      
      dataTable.draw();
    });
    
    // Фильтр по видам цен
    $(document).on("change", '#filter-price-type', function () {
      var priceTypeId = $(this).val();
      
      if (!priceTypeId) {
        $('#filter-price-agreements').trigger('change');
        return;
      }
      
      var agreementId = priceTypeFilter[priceTypeId]['UF_1C_ID'];
      $('#filter-price-agreements').val(agreementId).trigger('change');
      
    });
    
    // обработка формы по вводу текста в поиске
    $(document).on("keyup", '#search_field', dataTable.draw);

    // Очистка поиска
    $(document).on("click", '#clear_search_field', function (e) {
      e.preventDefault();
      $('#search_field').val('');
      dataTable.draw();
    });

    // Фильтр по размеру страницы
    $(document).on("click", '.page-len-button', function (e) {
      e.preventDefault();

      if (!$(this).hasClass('page-len-selected')) {
        $('.page-len-button').removeClass('page-len-selected');
        $(this).addClass('page-len-selected');
        var size = $(this).data('size');
        dataTable.page.len(size).draw();
        JSStorage.set(localStorageKeyPageSize, size);
      }
    });
    
    // Заполнение доступными объемами
    $(document).on("click", '#all-values', function () {
      dataTable.$("tr").each(function (index) {
        var limit = $(this).find(".js-div-source .js-volume").data("limit");
        
        if (limit && limit > 0) {
          $(this).find(".js-volume").val(0).trigger("change");
          $(this).find(".js-volume").each(function (index) {
            if (limit <= 0) return false;
            
            var max = $(this).data("max");
            
            if (limit >= max) {
              limit -= max;
              $(this).val(max).trigger("change");
            } else {
              $(this).val(limit).trigger("change");
              return false;
            }
          });
        }
      });
      
      updateTableTotals();
    });
  }

  function initSubmitButtons() {
    var jqXhr;

    $('button.js-submit').on('click', function () {
      if ($(this).hasClass('disabled')) {
        return false;
      }

      preloaderOpen();

      if (remains5050Access < remains5050.toFixed(2)) {
        preloaderClose();
        $('.conf-modal-advert').dialog('open');
        return false;
      }

      var transportationType = form.find('#transportation-type');
      var deliveryType = form.find('#delivery-type');
      var errors;

      let checkValue = function (el) {
        if (!el.val()) {
          el.focus().closest('.form-group').addClass('error').find('.help-block').removeClass('hidden');
        }
      };

      checkValue(transportationType);
      checkValue(deliveryType);

      errors = form.find('.error');
      if (errors.length) {
        $('html, body').animate({scrollTop: $('#agreement-data-block').position().top}, 300);
        preloaderClose();
        return false;
      }

      sendData(false, false);
    });
    
    function sendData(isUserAgreed, createNewRequest) {
      if (jqXhr || !hasSelectedProducts()) {
        preloaderClose();
        return false;
      }

      var data = form.serializeArray();
      var counterChosenProducts = 0;
      var dataArt = {};
      
      getActiveProductValues().each(function (index) {
        var obj = $(this).closest('tr');
        
        if (parseFloat($(this).val())) {
          // Собираем инпуты по строке только 1 раз
          if (dataArt.hasOwnProperty(obj.find('input.js-to-delete:checkbox').val())) {
            return true;
          }
                    
          var product = false;
          if ($(this).closest('.js-target-div').length === 0) {
            product = getProductData(index, obj, 0, ".js-div-source");
          } else {
            product = getProductData(index, obj, $(this).closest('.js-div-copy').index(), ".js-div-copy");
          }
          
          if (product) {
            data = $.merge(data, product);
            counterChosenProducts++;
          }
        }
      });

      if(data.length <= 0 || counterChosenProducts == 0) {
        preloaderClose();
        return;
      }

      if (isUserAgreed) {
        data.push({name: 'userAgreed', value: true});
      }

      if (createNewRequest) {
        data.push({name: 'createNewRequest', value: true});
      }

      data.push({name: 'action', value: 'save'});
      data.push({name: 'additionId', value: additionId});
      
      errorEL = errorTR = false;

      jqXhr = $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function (response) {
          if (response.exceededLimit) {
            openExceededLimitDialog(response.products);
          } else if (response.conflict) {
            if (response.errorType === 'reserves') {
                openReservesConflictDialog(response.products);
            }
            if (response.errorType === 'orders') {
                openOrdersConflictDialog(response.products);
            }
          } else if (response.hasCrossing) {
            openDialog(response.additions, counterChosenProducts);
          } else if (response.isEmpty) {
            openEmptyConflictDialog();
          } else if(response.error) {
            console.error(response.error);
          } else {
            if (response.additionIds || response.crossAdditions) {
              var additionIds = response.additionIds;
              var crossAdditionsIds = response.crossAdditions;
              location.href = successUrl.replace('#AGREEMENT_ID#', additionId) +
                (additionIds && additionIds.length > 0 ? '&' + jQuery.param({aIds: additionIds}) : '') +
                (crossAdditionsIds && crossAdditionsIds.length > 0 ? '&' + jQuery.param({caIds: crossAdditionsIds}) : '');
            }
          }
        },
        error: function (error) {
          console.error(error);
          return false;
        },
        complete: function () {
          jqXhr = null;
          preloaderClose();
        }
      });
    }

    function getProductData(index, tr, rowIndex, parentClass) {
      var product = [];
      var productField = tr.find(parentClass + " input.js-volume")[rowIndex];
      var limit = $(productField).data("limit");
      var volume = $(productField).val();
      var productId = $(productField).data("id");
      var priceTypeId = $(tr.find(parentClass + " .price-type-select option:selected")[rowIndex]).val();
      
      if (!priceTypeId) return false;
      
      var priceType = priceTypes[productId][priceTypeId];
      var priceId = priceType['PRICE_ID'] ? priceType['PRICE_ID'] : "";
      var priceAgreementId = priceType['AGREEMENT_1C_ID'] ? priceType['AGREEMENT_1C_ID'] : "";
      var fiftyFyfty = $(tr.find(parentClass + " .js-50-50")[rowIndex]).prop("checked");
      var specialPrice = $(tr.find(parentClass + " .js-special-price")[rowIndex]).prop("checked");
      var measure = tr.find("input[name='product[" + productId + "][measure]']").val();
      
      product.push({name: "product[" + productId + "][id]", value: productId});
      product.push({name: "product[" + productId + "][volume]", value: volume});
      product.push({name: "product[" + productId + "][priceAgreementId]", value: priceAgreementId});
      product.push({name: "product[" + productId + "][priceId]", value: priceId});
      product.push({name: "product[" + productId + "][priceTypeId]", value: priceTypeId});
      product.push({name: "product[" + productId + "][fiftyFyfty]", value: fiftyFyfty ? 1 : 0});
      product.push({name: "product[" + productId + "][specialPrice]", value: specialPrice ? 1 : 0});
      product.push({name: "product[" + productId + "][limit]", value: limit});
      product.push({name: "product[" + productId + "][measure]", value: measure});

      return product;
    }
    
    function openDialog(data, counterChosenProducts) {
      let dialogContainer = $('<div>');
      let outputTable = BX.message('CROSS_MESSAGE_TEMPLATE')
        .replace('#COUNT_CHOSEN#', counterChosenProducts)
        .replace('#PRODUCTS_ENDING#', declOfNum(counterChosenProducts, BX.message('productsEndingArr')));
      let foundProductsInAdditonals = 0;
      let rowCounter = 1;
      let product, additionalProducts, addition;

      outputTable += '<table autofocus class="table table-bordered table-striped table-hover mt-1 modal-products">' +
        '<thead>' +
        '<th>№</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_NAME_OF_PRODUCT') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_PRICE_AGREEMENT') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_PRICE_TYPE') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_ADDITIONAL_NUMBER') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_ADDITIONAL_AMOUNT') + '</th>' +
        '</thead>';

      for (let additionIndex in data) {
        addition = data[additionIndex];
        for (let productIndex in addition.products) {
          product = products[productIndex];
          additionalProducts = addition.products[productIndex];

          outputTable += '<tr>';
          outputTable += '<td>' + rowCounter + '</td>';
          outputTable += '<td>' + product.NAME + '<span class="product_info">' + BX.message('PRODUCT_NUMBER') + ': ' + product.CODE + '</span></td>';
          outputTable += '<td class="text-center">' + (additionalProducts.priceAgreementName ? additionalProducts.priceAgreementName : '-') + '</td>';
          outputTable += '<td class="text-center">' + (additionalProducts.priceTypeName ? additionalProducts.priceTypeName : '-') + '</td>';
          outputTable += '<td> ' + BX.message('K_C_ADDITIONAL_MODAL_ADDITIONAL_NUM') + addition.info.ID +
            (addition.info.NUMBER ? ' (' + addition.info.NUMBER + ')' : '') + '</td>';
          outputTable += '<td class="text-center"> ' + additionalProducts.volume + '</td>';
          outputTable += '</tr>';

          if (parseFloat(additionalProducts.volume) > 0) {
            foundProductsInAdditonals++;
          }
          rowCounter++;
        }
      }
      outputTable += '</table>';

      dialogContainer.append(outputTable.replace('#COUNT_IN_ADDITIONALS#', foundProductsInAdditonals));

      initDialog(dialogContainer, {
        width: '60%',
        title: BX.message('K_C_ADDITIONAL_MODAL_TITLE'),
        buttons: [
          {
            text: BX.message('K_C_ADDITIONAL_MODAL_BUTTON_OK_TITLE'),
            "class": "btn btn-round btn-info col-2",
            click: function () {
              preloaderOpen();
              sendData(true, false);
              $(this).dialog("close");
            }
          },
          {
            text: BX.message('K_C_ADDITIONAL_MODAL_BUTTON_CANCEL_TITLE'),
            "class": "btn btn-round btn-light ml-1 col-2",
            click: function () {
              $(this).dialog("close");
            }
          }
        ]
      }, true);

      dialogContainer.dialog('open').css({height: "350px", overflow: "auto"});

    }

    function openExceededLimitDialog(data) {
      let dialogContainer = $('<div id="exceededLimitDialog">');
      let outputTable = BX.message('EXCEEDED_LIMIT_MESSAGE_TEMPLATE');
      let i = 1;
      let product, productData, measureTitle;
      errorEL = true;

      outputTable += '<table autofocus class="table table-bordered table-striped table-hover mt-1 modal-products">' +
        '<thead>' +
        '<th>№</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_NAME_OF_PRODUCT') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_AVAILABLE_VOLUME') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_DECLARED_VOLUME') + '</th>' +
        '</thead>';

      productsError = [];
      for (let productIndex in data) {
        product = products[productIndex];
        productData = data[productIndex];
        productsError[productIndex] = productIndex;
        measureTitle = (storeType === 'packing') ? BX.message('UNIT_PALLET') : measures[productData.measure].SYMBOL

        outputTable += '<tr>';
        outputTable += '<td>' + i + '</td>';
        outputTable += '<td>' + product.NAME + '<span class="product_info">' + BX.message('PRODUCT_NUMBER') + ': ' + product.CODE + '</span></td>';
        outputTable += '<td class="text-center">' + productData.limit + ' ' + measureTitle + '</td>';
        outputTable += '<td class="text-center"> '
          + (storeType === 'pouring' ? $.number(productData.volume, 3, '.', ' ') : productData.volume) + ' ' + measureTitle + '</td>';
        outputTable += '</tr>';
        i++;
      }
      outputTable += '</table>';
      dialogContainer.append(outputTable);

      initDialog(dialogContainer, {
        width: "60%",
        title: BX.message('K_C_ADDITIONAL_MODAL_TITLE'),
        buttons: [
          {
            text: BX.message('EXCEEDED_LIMIT_MESSAGE_TEMPLATE_INCREASE_LIMIT'),
            "class": "btn btn-round btn-info",
            click: function () {
              preloaderOpen();
              sendData(true, true);
              $(this).dialog("close");
            }
          },
          {
            text: BX.message('EXCEEDED_LIMIT_MESSAGE_TEMPLATE_RESET_LIMIT'),
            "class": "btn btn-round btn-info ml-1",
            click: function () {
              paintProductsError();
              $(this).dialog("close");
            }
          }
        ]
      }, true);

      dialogContainer.dialog('open').css({height: "350px", overflow: "auto"});
    }

    function openReservesConflictDialog(data) {
      let dialogContainer = $('<div id="reservesConflictDialog">');
      let outputTable = BX.message('K_C_ADDITIONAL_EDIT_PRODUCTS_RESERVES_CONFLICT_TEMPLATE');
      let i = 1;
      let product, productData, measureTitle;
      errorTR = true;

      outputTable += '<table autofocus class="table table-bordered table-striped table-hover mt-1 modal-products">' +
        '<thead>' +
        '<th>№</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_NAME_OF_PRODUCT') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_CURRENT_VOLUME') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_REQUESTED_VOLUME') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_COMMENT') + '</th>' +
        '</thead>';

      for (let productIndex in data) {
        product = products[productIndex];
        productData = data[productIndex];
        measureTitle = (storeType === 'packing') ? BX.message('UNIT_PALLET') : measures[productData.measure].SYMBOL

        outputTable += '<tr>';
        outputTable += '<td>' + i + '</td>';
        outputTable += '<td>' + product.NAME + '<span class="product_info">' + BX.message('PRODUCT_NUMBER') + ': ' + product.CODE + '</span></td>';
        outputTable += '<td class="text-center">' + productData.VOLUME_OLD + ' ' + measureTitle + '</td>';
        outputTable += '<td class="text-center"> '
          + (storeType === 'pouring' ? $.number(productData.VOLUME, 3, '.', ' ') : productData.VOLUME) + ' ' + measureTitle + '</td>';
        outputTable += '<td class="text-center">'
          + (productData.ADDITION_BX_ID ? BX.message('K_C_ADDITIONAL_EDIT_PRODUCTS_MOVE_TO_ADDITION') + productData.ADDITION_BX_ID : '') + '</td>';
        outputTable += '</tr>';
        i++;
      }
      outputTable += '</table>';
      dialogContainer.append(outputTable);

      initDialog(dialogContainer, {
        width: "60%",
        title: BX.message('K_C_ADDITIONAL_MODAL_TITLE'),
        buttons: [
          {
            text: BX.message('K_C_ADDITIONAL_MODAL_BUTTON_BACK'),
            "class": "btn btn-round btn-info",
            click: function () {
              productsError = [];
              for (let productIndex in data) {
                productsError[productIndex] = productIndex;
              }
              paintProductsError();
              $(this).dialog("close");
            }
          }
        ]
      }, true);

      dialogContainer.dialog('open').css({height: "350px", overflow: "auto"});
    }

    function openEmptyConflictDialog() {
      let dialogContainer = $('<div id="emptyDialog">');
      let outputTable = BX.message('K_C_ADDITIONAL_EDIT_DIALOG_EMPTY');

      dialogContainer.append(outputTable);

      initDialog(dialogContainer, {
        width: "60%",
        title: BX.message('K_C_ADDITIONAL_MODAL_TITLE'),
        buttons: [
          {
            text: BX.message('K_C_ADDITIONAL_EDIT_DIALOG_EMPTY_BTN'),
            "class": "btn btn-round btn-info",
            click: function () {
              $(this).dialog("close");
            }
          }
        ]
      }, true);

      dialogContainer.dialog('open').css({height: "100px", overflow: "auto"});
    }

    function openOrdersConflictDialog(data) {
      let dialogContainer = $('<div id="ordersConflictDialog">');
      let outputTable = BX.message('K_C_ADDITIONAL_EDIT_PRODUCTS_ORDERS_CONFLICT_TEMPLATE');
      let i = 1;
      let product, productData, measureTitle;
      errorTR = true;

      outputTable += '<table autofocus class="table table-bordered table-striped table-hover mt-1 modal-products">' +
        '<thead>' +
        '<th>№</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_NAME_OF_PRODUCT') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_ORDERED_VOLUME') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_REQUESTED_VOLUME') + '</th>' +
        '</thead>';

      for (let productIndex in data) {
        product = products[productIndex];
        productData = data[productIndex];
        measureTitle = (storeType === 'packing') ? BX.message('UNIT_PALLET') : measures[productData.measure].SYMBOL

        outputTable += '<tr>';
        outputTable += '<td>' + i + '</td>';
        outputTable += '<td>' + product.NAME + '<span class="product_info">' + BX.message('PRODUCT_NUMBER') + ': ' + product.CODE + '</span></td>';
        outputTable += '<td class="text-center">' + productData.VOLUME_ORDERED + ' ' + measureTitle + '</td>';
        outputTable += '<td class="text-center"> '
          + (storeType === 'pouring' ? $.number(productData.VOLUME, 3, '.', ' ') : productData.VOLUME) + ' ' + measureTitle + '</td>';
        outputTable += '</tr>';
        i++;
      }
      outputTable += '</table>';
      dialogContainer.append(outputTable);

      initDialog(dialogContainer, {
        width: "60%",
        title: BX.message('K_C_ADDITIONAL_MODAL_TITLE'),
        buttons: [
          {
            text: BX.message('K_C_ADDITIONAL_MODAL_BUTTON_BACK'),
            "class": "btn btn-round btn-info",
            click: function () {
              productsError = [];
              for (let productIndex in data) {
                productsError[productIndex] = productIndex;
              }
              paintProductsError();
              $(this).dialog("close");
            }
          }
        ]
      }, true);

      dialogContainer.dialog('open').css({height: "350px", overflow: "auto"});
    }
  }
  
  $.extend(Module, {
    init: function init(options) {
      ajaxUrl = options.ajaxUrl;
      measures = options.measures;
      remains5050Access = parseFloat(options.remains5050);
      successUrl = options.successUrl;
      advert5050 = options.advert5050;
      afterSaveUrl = options.afterSaveUrl;
      additionProductsEdit = options.additionProducts;
      orderProducts = options.orderProducts;
      additionId = options.additionId;
      reserves = options.reserves;

      initInfoTooltips();

      extendContractChangeEvents();
      initDialogs();
      initDataTable();
      initSubmitButtons();

      $("input#search-not-empty").iCheck({checkboxClass: 'icheckbox_flat-blue'});
    }
  });
})((Additionals = window.Additionals || {}).Edit = Additionals.Edit || {}, ContractSelect, $);
