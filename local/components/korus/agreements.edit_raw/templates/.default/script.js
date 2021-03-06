(function (Module, ContractSelect, $) {
  var form = $('#gpnsm_additional_raw'),
    ajaxUrl,
    ajaxUrlRaw,
    afterSaveRawUrl,
    afterSaveUrl,
    dataTableBlock = $('.js-data-table-block'),
    dataTable,
    sendTriggerPriceAgreementSelect = true,
    ndsCoef = 1.2,
    fiftyCoef = 0.9,
    volumeDef = 1000000000,
    remains5050Access = 0,
    remains5050 = 0,
    storeId = 0,
    rawId = 0,
    measures,
    successUrl,
    priceTypes = [],
    additionProductsRaw = [],
    deletedList = [],
    saveType = "save",
    localStorageKeyPageSize = 'AGREEMENTS_RAW',
    previous = {
      period: form.find('#period').val(),
      store: form.find('#store').val()
    },
    products = [],
    brands = [],
    priceAgreementsFilter = [],
    priceTypeFilter = [],
    productsError = [],
    tdHeight = 50,
    storeType = {},
    isReloadDataTable = true;
  var searchText = '',
      brandFilter = '',
      isSearchNotEmpty = false;

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
          text: "????????????",
          "class": "btn btn-round grey btn-outline-secondary col-5 pull-right",
          click: function () {
            $(this).dialog("close");
          }
        },
        {
          text: "????",
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
      method : 'post',
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
          text: "????????????",
          "class": "btn btn-round grey btn-outline-secondary col-5 pull-right",
          click: function () {
            form.find('#period').val(previous.period).trigger('change.select2');
            $(this).dialog("close");
          }
        },
        {
          text: "????",
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
          text: "????????????",
          "class": "btn btn-round grey btn-outline-secondary col-5 pull-right",
          click: function () {
            form.find('#store').val(previous.store).trigger('change.select2');
            $(this).dialog("close");
          }
        },
        {
          text: "????",
          "class": "btn btn-round btn-info col-5 pull-right",
          click: function () {
            reloadDataTable();
            isReloadDataTable = true;
            $(this).dialog("close");
          }
        }
      ]
    });

    initDialog(".conf-modal-advert", {
      buttons: [
        {
          text: "????",
          "class": "btn btn-round btn-info col-5 pull-right",
          click: function () {
            $(this).dialog("close");
          }
        }
      ]
    });
  }

  function initSelects() {
    form.find('#transportation-type')
      .on('change', function () {
        $('#delivery-point').html('').change();
      
        var transportationTypeId = $('#transportation-type').val();
        if (transportationTypeId === 'car') {
          var inputLabel = $('#obj-delivery-address .obj-delivery-address');
          $(inputLabel).html($(inputLabel).text() + ' ' + '<span class="required">*</span>');
        } else {
          var el = $('#delivery-address');
          if (el.closest('.form-group.error')) {
            el.closest('.form-group').removeClass('error').find('.help-block').addClass('hidden');
          }
          $('#obj-delivery-address .obj-delivery-address').find('.required').remove();
        }
      });

    initSelect2('#period, #store, #transportation-type, #delivery-type', {});
    initSelect2('#receiver', {
      allowClear: true, 
      minimumResultsForSearch: 1, 
      language: {noResults: function (params) {return BX.message('K_C_ADDITIONAL_CREATE_FORM_RECEIVER_NOT_ELEMENT');}}
    });

    initSelect2('#delivery-point', {
      allowClear: true,
      minimumResultsForSearch: 1,
      ajax: {
        url: '/agreements/create_agreement_ajax.php',
        type: 'POST',
        dataType: 'json',
        data: function (params) {
          return {
            action: 'getDeliveryPoints',
            q: params.term,
            tt: $('#transportation-type').val()
          };
        },
        processResults: function (data) {
          return {
            results: data.items
          };
        }
      },
      language: {
        errorLoading: function () {
          return '?????? ??????????????????????';
        },
        searching: function() {
          return "?????????????? ???????????????? ???????????? ????????????????";
        },
        noResults: function (params) {
          return "?????? ??????????????????????";
        },
        inputTooShort: function (params) {
          return "?????? ??????????????????????";
        }
      }
    });

    initSelect2('#delivery-address', {
      allowClear: true,
      minimumResultsForSearch: 1,
      ajax: {
        url: '/agreements/create_agreement_ajax.php',
        type: 'POST',
        dataType: 'json',
        data: function (params) {
          return {
            action: 'getDeliveryAddresses',
            q: params.term
          };
        },
        processResults: function (data) {
          return {
            results: data.items
          };
        }
      },
      language: {
        errorLoading: function () {
          return '?????? ??????????????????????';
        },
        searching: function() {
          return "?????????????? ?????????? ????????????????";
        },
        noResults: function (params) {
          return "?????? ??????????????????????";
        },
        inputTooShort: function (params) {
          return "?????? ??????????????????????";
        }
      }
    });

    form.find('#period').on('change.select2', function () {
      if (hasSelectedProducts()) {
        $('.conf-modal-change-period').dialog('open');
      } else {
        updateStoreList();
      }
    });
    
    form.find('#store').on('change.select2', function () {
      if (hasSelectedProducts()) {
        $('.conf-modal-change-store').dialog('open');
      } else {
        reloadDataTable();
        isReloadDataTable = true;
      }
    });
    
    form.find('#transportation-type, #delivery-type, #delivery-address, #delivery-point').on('change', function () {
      if ($(this).val()) {
        $(this)
          .parents('.form-group').removeClass('error').end()
          .siblings('.help-block').addClass('hidden');
      }
    });
  }

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
      fiftyFifty = el.data("fiftyFifty"),
      priceTypeId = $(tr.find(parentClass + " .price-type-select")[index]).val(),
      priceType;
    
    if (typeof keyCode === 'undefined') {
      keyCode = '';
    }

    if (isPieces) {
      volume *= el.data('pallet-rate');
      if (priceTypes[productId] && priceTypes[productId][priceTypeId]) {
        priceType = priceTypes[productId][priceTypeId];
        price = priceType['PRICE'] * ndsCoef;
      }
      $(tr.find(parentClass + " .js-pieces")[index]).text($.number(volume, 0, '.', ' '));
    }

    if (fiftyFifty) {
      price *= fiftyCoef;
    }

    $(tr.find(parentClass + " .price-piece-total")[index]).text($.number((price * volume), 2, '.', ' '));
    tr.find(".js-volume").each(function() {
      currentVolume += Math.abs($(this).val());
    });
    
    if (limit >= currentVolume) {
      productsError[productId] = '';
      tr.find(".js-volume").css('background', '#FFF');
    }

    updateTableTotals();

    // 37, 39 - ???????? ???????????? "?????????????? ??????????" ?? "?????????????? ????????????"
    var st;
    if (keyCode) {
      if ([37, 39].indexOf(keyCode) >= 0) {
        return false;
      }
      st = setTimeout(function () {
        controlMaxVolume(el);
      }, 700);
    } else {
      clearTimeout(st);
      controlMaxVolume(el);
    }
  }
  
  function controlMaxVolume(el) {
    var id = el.data("id");
    var priceId = el.data("priceTypeId");
    var palletRate = parseInt(products[id]['PALLET_RATE']);
    var max = 0;
    var priceTypeId = el.data("price-type-id");

    if (!priceTypeId) return;

    if (priceTypes[id][priceTypeId]['VOLUME'] !== undefined) {
      if (priceTypes[id][priceTypeId]['VOLUME'] == 0) {
        $(el).closest(".align-items-center").find(".max-count").text("???");
        $(el).trigger("touchspin.updatesettings", {max: volumeDef});
        $(el).data({"max": volumeDef});
        return;
      } else {
        max = priceTypes[id][priceTypeId]['VOLUME'];
      }
    }

    var volumeMax = 0;
    var tr = el.closest('tr');
    var difference = 0;
    
    tr.find('input.js-volume').each(function (i, obj) {
      var priceIdEa = $(obj).data("priceTypeId");

      if (priceIdEa == priceId) {
        volumeMax += parseFloat($(obj).val());
      }
    });
    
    if (storeType == 'packing') {
      max = parseInt(max / palletRate)
    }
    
    difference = max - volumeMax;
    
    tr.find('input.js-volume').each(function (i, obj) {
      var priceIdEa = $(obj).data("priceTypeId");
      if (priceIdEa != priceId) {
        return true;
      }

      var valEa = parseFloat($(obj).val());
      var newMax = 0;
      if (difference >= 0) {
        newMax = difference + valEa;
      } else {
        newMax = max >= valEa ? valEa : max;
        max = max > valEa ? max - valEa : 0;
      }

      newMax = storeType == 'packing' ? newMax : parseFloat(newMax).toFixed(3);

      $(obj).closest(".align-items-center").find(".max-count").text(newMax);
      $(obj).data({"max": newMax});
    });
    
    tr.find('input.js-volume').each(function (i, obj) {
      var priceIdEa = $(obj).data("priceTypeId");
      if (priceIdEa != priceId) {
        return true;
      }

      $(obj).trigger("touchspin.updatesettings", {max: $(obj).data("max")});
    });
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
  
  $(document).on('click', '.delete-row', function () {
    deleteCopyRow($(this).closest("tr"), $(this).closest('div.js-div-copy').index());   
  });
  
  $(document).on('focus', '.js-volume', function () {
    if($(this).val() == 0) {
      $(this).val('');
    }
  });

  $(window).on('resize', function () {
    setTimeout(function () {
      JsDataTableRender.recalcWidth();
      updateDeleted();
    }, 500);
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
    var priceId = (priceTypeId && priceType['PRICE_ID']) ? priceType['PRICE_ID'] : 0;
    var advert = $(tr.find(parentClass + " .js-50-50")[index]).prop("checked");
    var maxValue = 0;
    
    if (priceTypeId) {
      maxValue = (priceType['VOLUME'] !== undefined && priceType['VOLUME'] > 0) ? priceType['VOLUME'] : volumeDef;
    }

    var palletRate = parseInt(products[productId]['PALLET_RATE']);

    if (!priceTypeId || priceType['TYPE'] == "individual") {
      $(tr.find(parentClass + " .js-50-50")[index]).prop("disabled", true).prop("checked", false).iCheck('update');
      $(tr.find(parentClass + " .js-special-price")[index]).prop("disabled", true).prop("checked", false).iCheck('update');
    } else {
      $(tr.find(parentClass + " .js-50-50")[index]).prop("disabled", false).iCheck('update');
      $(tr.find(parentClass + " .js-special-price")[index]).prop("disabled", false).iCheck('update');
    }
    
    price *= advert == 1 ? fiftyCoef : 1;

    maxValue = storeType == 'packing' ? parseInt(maxValue / palletRate) : parseFloat(maxValue).toFixed(3);

    $(productField).trigger("touchspin.updatesettings", {max: maxValue});
    $(productField).data({"max": maxValue});
    $(productField).data({"price-type-id": priceTypeId ? priceTypeId : ''});
    $(productField).data({"price-id": priceId});
    $(productField).data({"price": price * ndsCoef});
    $(tr.find(parentClass + " .price-piece")[index]).text($.number(price, 2, '.', ' '));
    $(tr.find(parentClass + " .price-piece-nds")[index]).text($.number((price * ndsCoef), 2, '.', ' '));
    $(tr.find(parentClass + " .price-piece-total")[index]).text($.number(0, 2, '.', ' '));

    $(tr).find("input.js-volume").each(function (i, elem) {
      $(elem).trigger('change');
    });
  }
  
  /**
   * ?????????????????? 50/50
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
   * ?????????????????? ?????????????????????? ????????
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

    $(productField).data({"fifty-fifty": fiftyFifty});

    var priceType = priceTypes[productId][priceTypeId];
    var price = priceType['PRICE'] ? priceType['PRICE'] : 0;

    if (fiftyFifty) {
      price *= fiftyCoef;
    }

    $(tr.find(parentClass + " .price-piece")[index]).text($.number(price, 2, '.', ' '));
    $(tr.find(parentClass + " .price-piece-nds")[index]).text($.number((price * ndsCoef), 2, '.', ' '));
  }

  function initDataTable() {
    var selectedPriceType = {};

    function getPriceTypeSelect(productIndex, defaultOption, defProduct) {
      selectedPriceType = {};
      var select = $('<select>', {class: "price-type-select", "data-placeholder": "???????????????? ??????????"});
      
      if (!defaultOption) {
        select.append($("<option>"));
      }

      if (priceTypes[productIndex]) {
        $.each(priceTypes[productIndex], function (index, value) {
          var option = getPriceTypeOption(value);

          if ((defaultOption && value["DEFAULT"] == 1)
            || defProduct["PRICE_TYPE"] == value["PRICE_TYPE_1C_ID"]) {
            option.attr({"selected": "selected"});
            selectedPriceType = value;
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

    // ???????????? ??????????????
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
      var fifty = (defProduct && defProduct['ADVERT_50_50']) ? defProduct['ADVERT_50_50'] : 0;
      return $('<input>', {
        type: 'text',
        class: 'touchspin-quantity decimal-inputmask form-control js-volume ' + (defProduct['ID']? "raw" : ""),
        value: defProduct['VOLUME'] !== undefined ? defProduct['VOLUME'] : 0,
        name: 'product[' + productIndex + '][' + name + '][]',
        'data-limit': max > 0 ? max : 0,
        'data-max': maxVolume,
        'data-fifty-fifty': fifty,
        'data-id': productIndex,
        'data-pieces': isPieces,
        'data-weight': productInfo.WEIGHT,
        'data-pallet-rate': productInfo.PALLET_RATE,
        'data-bts-button-down-class': 'btn bootstrap-touchspin-down btn-light',
        'data-bts-button-up-class': 'btn bootstrap-touchspin-up btn-light',
        'data-price': getDefaultPrice(defProduct) * ndsCoef
      });
    }

    function getVolumeBlock(productIndex, name, productInfo, isPieces, max, measureTitle, defProduct, maxVolume) {
      var palletRate = parseInt(products[productInfo['CODE']]['PALLET_RATE']);
      maxVolume = maxVolume > 0 ? maxVolume : (defProduct['ID'] ? volumeDef : 0);
      var maxVolumeNew = storeType == 'packing' ? parseInt(maxVolume / palletRate) : parseFloat(maxVolume).toFixed(3);
      var symbol = maxVolume == volumeDef ? "???" : maxVolumeNew;
      
      return $('<div>', {class: 'align-items-center', style: 'display: flex'})
        .append(
          $('<div>', {class: "input-group bootstrap-touchspin",  style: "width:150px"})
            .append(getVolumeInput(productIndex, name, productInfo, isPieces, max, maxVolumeNew, defProduct))
        )
        .append($('<span>', {style: "padding-left: 10px", html: measureTitle}))
        .append($('<span>', {style: "padding-left: 10px;width: 85px;text-align: left;", html: "max - "}).append($('<span>', {class: "max-count", html: symbol})));
    }

    function getProductInfoHtml(product) {
      var additionHtml = BX.message('PRODUCT_NUMBER') + ': ' + product.CODE;

      if (product.CAPACITY && measures[product.MEASURE].SYMBOL !== '??') {
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
        url: ajaxUrlRaw,
        type: 'POST',
        data: function ( data ) {
          return $.extend(
            {}, data, {
              action: 'getProducts',
              period: form.find('#period').val(),
              store: form.find('#store').val(),
              rawId: rawId
            }
          );
        }
      },
      initComplete: function() {
        $(".page-len").append("???????????????? ????: ").css({"margin": "20px"})
          .append($("<a>", {href: '#', class: 'page-len-button ml-1', 'data-size': 20, text: 20}))
          .append($("<a>", {href: '#', class: 'page-len-button ml-1', 'data-size': 50, text: 50}))
          .append($("<a>", {href: '#', class: 'page-len-button ml-1', 'data-size': 100, text: 100}));
        $(".page-len").find('*[data-size='+pageLength+']').addClass('page-len-selected');  
      
        $(".button-all-values")
          .append($("<button>", {"id": "all-values", "type": "button", "class": "btn btn-round btn-warning-gpnsm btn-info", "text": BX.message('BTN_ALL_VOLUES')}));

        JsDataTableRender.fixTableHeader();
        updateDeleted();
      },
      fnDrawCallback: function (oSettings) {
        var paginate = $(oSettings.nTableWrapper).find('.dataTables_paginate');
        
        if (oSettings._iDisplayLength >= oSettings.fnRecordsDisplay()) {
          paginate.parent().addClass("hidden");
        } else {
          paginate.parent().removeClass("hidden");
        }
                
        // ?????? ?????????????? ?? ???????????? ???????????? ????????????(???????????? ????????????????????)
        var changeClass8 = dataTable.$('.js-td-copy-8');
        if (storeType !== 'packing') {
          $(changeClass8).addClass('js-td-copy-volume').removeClass('js-td-copy-pieces');
        } else {
          $(changeClass8).addClass('js-td-copy-pieces').removeClass('js-td-copy-volume');
        }

        // ???????????????????? ?????????????????? ?????????? 
        rebuildSelectPriceTypes();
        initDataTableContent();
        updateTableTotals();
        
        if (sendTriggerPriceAgreementSelect) {
            $('.js-volume.raw').trigger('change');
            $('.price-agreement-select.noraw').trigger('change');
        }
        sendTriggerPriceAgreementSelect = true;

        storeId = $('#store').val();
        paintProductsError();
        
        if (isReloadDataTable) {
          isReloadDataTable = false;
          JsDataTableRender.fixTableHeader();
          updateDeleted();
        } else {
          JsDataTableRender.recalcWidth();
        }
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
        {className: 'text-center js-td-copy js-td-copy-action', 'targets': [16]},
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
            return '<span class="td-matched-count">' + count + '</span> ' + measures[products[row.product].MEASURE].SYMBOL;
          }
        },
        {
          targets: 5,
          data: 'available',
          render: function (data, type, row) {
            return '<span class="td-available-pallet">' + data + '</span> ' + BX.message('UNIT_PALLET') + '.';
          }
        },
        {
          targets: 6,
          data: 'available',
          render: function (data, type, row) {
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
            var targetDiv = "";
            var max = 0;
            if (priceTypes[data]) {
              var keys = Object.keys(priceTypes[data]);
              if (keys.length < 2) {
                var priceType = priceTypes[data][keys[0]];
                max = (priceType['VOLUME'] !== undefined && priceType['VOLUME'] > 0) ? priceType['VOLUME'] : volumeDef;
              }
            }
            
            if (additionProductsRaw[storeId] && additionProductsRaw[storeId][data]) {
              $.each(additionProductsRaw[storeId][data], function(index, value) {
                var html = storeType === 'packing'
                    ? getVolumeBlock(data, 'volume', products[data], 1, row.available, BX.message('UNIT_PALLET'), value, max)[0].outerHTML
                    : '';
                if (index == 0) {
                  divSource += html
                } else {
                  targetDiv += '<div class="js-div-copy">' + html + '</div>';
                }
              });
            } else {
              divSource += storeType === 'packing'
              ? getVolumeBlock(data, 'volume', products[data], 1, row.available, BX.message('UNIT_PALLET'), [], max)[0].outerHTML
              : '';
          }
                          
            return '<div class="js-div-source">' + divSource + '</div><div class="js-target-div">' + targetDiv + '</div>';
          }
        },
        {
          targets: 8,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            var targetDiv = "";
            var max = 0;
            if (priceTypes[data]) {
              var keys = Object.keys(priceTypes[data]);
              
              if (keys.length < 2) {
                var priceType = priceTypes[data][keys[0]];
                max = (priceType['VOLUME'] !== undefined && priceType['VOLUME'] > 0) ? priceType['VOLUME'] : volumeDef;
              }
            }
            
            if (additionProductsRaw[storeId] && additionProductsRaw[storeId][data]) {
              $.each(additionProductsRaw[storeId][data], function(index, value) {
                var html = storeType !== 'packing'
                  ? getVolumeBlock(data ,'volume', products[data], row.available,row.available, measures[products[row.product].MEASURE].SYMBOL, value, max)[0].outerHTML
                  : $('<span>', {class: 'js-pieces', html: 0})[0].outerHTML +
                ' ' + measures[products[row.product].MEASURE].SYMBOL;
                if (index == 0) {
                  divSource += html;
                } else {
                  targetDiv += '<div class="js-div-copy"><p>' + html + '</p></div>';
                }
              });
            } else {
              divSource += storeType !== 'packing'
                ? getVolumeBlock(data, 'volume', products[data], 0, row.available, measures[products[row.product].MEASURE].SYMBOL, [], max)[0].outerHTML
                : $('<span>', {class: 'js-pieces', html: 0})[0].outerHTML +
                  ' ' + measures[products[row.product].MEASURE].SYMBOL;
            }
                    
            return '<div class="js-div-source"><p>' + divSource + '</p></div><div class="js-target-div">' + targetDiv +'</div>';
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
          render: function (data, type, row) {
            var divSource = "";
            var targetDiv = "";
            var keys = Object.keys(priceTypes[data]);
            
            if (additionProductsRaw[storeId] && additionProductsRaw[storeId][data]) {
              $.each(additionProductsRaw[storeId][data], function(index, value) {
                var html = getPriceTypeSelect(data, false, value)[0].outerHTML;
                if (index == 0) {
                  divSource += html;
                } else {
                  targetDiv += '<div class="js-div-copy">' + html + '</div>';
                }
              });
            } else {
              divSource += getPriceTypeSelect(data, keys.length < 2, [])[0].outerHTML;
            }
            
            return '<div class="js-div-source">' + divSource + '</div><div class="js-target-div">' + targetDiv +'</div>';
          }
        },
        {
          targets: 11,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            var targetDiv = "";
            var keys = Object.keys(priceTypes[data]);
            
            if (additionProductsRaw[storeId] && additionProductsRaw[storeId][data]) {
              $.each(additionProductsRaw[storeId][data], function(index, value) {
                var disabled = (priceTypes[value['ID']]
                  && priceTypes[value['ID']] !== undefined
                  && priceTypes[value['ID']][value['PRICE_TYPE']] !== undefined
                  && priceTypes[value['ID']][value['PRICE_TYPE']]['TYPE'] == "individual");
                var html = getCheckbox(data, '50-50', 1, 'js-50-50', value['ADVERT_50_50'], disabled)[0].outerHTML;
                if (index == 0) {
                  divSource += html;
                } else {
                  targetDiv += '<div class="js-div-copy">' + html + '</div>';
                }
              });
            } else {
              divSource += getCheckbox(data, '50-50', 1, 'js-50-50', false, keys.length > 1)[0].outerHTML;
            }
            
            return '<div class="js-div-source">' + divSource + '</div><div class="js-target-div">' + targetDiv +'</div>';
          }
        },
        {
          targets: 12,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            var targetDiv = "";
            var keys = Object.keys(priceTypes[data]);

            if (additionProductsRaw[storeId] && additionProductsRaw[storeId][data]) {              
              $.each(additionProductsRaw[storeId][data], function(index, value) {
                var disabled = (priceTypes[value['ID']] 
                  && priceTypes[value['ID']] !== undefined
                  && priceTypes[value['ID']][value['PRICE_TYPE']] !== undefined
                  && priceTypes[value['ID']][value['PRICE_TYPE']]['TYPE'] == "individual");
                var html = getCheckbox(data, 'specialPrice', 1, 'js-special-price', value['SPECIAL_PRICE'], disabled)[0].outerHTML;
                if (index == 0) {
                  divSource += html;
                } else {
                  targetDiv += '<div class="js-div-copy">' + html + '</div>';
                }
              });
            } else {
              divSource += getCheckbox(data, 'specialPrice', 1, 'js-special-price', false, keys.length > 1)[0].outerHTML;
            }
            
            return '<div class="js-div-source">' + divSource + '</div><div class="js-target-div">' + targetDiv +'</div>';
          }
        },
        {
          targets: 13,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            var targetDiv = "";
            
            if (additionProductsRaw[storeId] && additionProductsRaw[storeId][data]) {
              $.each(additionProductsRaw[storeId][data], function(index, value) {
                var html = "<span class='price-piece'>" + $.number(getDefaultPrice(value), 2, '.', ' ') + "</span> ??????.";
                if (index == 0) {
                  divSource += html;
                } else {
                  targetDiv += '<div class="js-div-copy price-fixed">' + html + '</div>';
                }
              });
            } else {
              divSource += "<span class='price-piece'>0.00</span> ??????.";
            }
            
            return '<div class="js-div-source price-fixed">' + divSource + '</div><div class="js-target-div">' + targetDiv +'</div>';
          }
        },
        {
          targets: 14,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            var targetDiv = "";
            
            if (additionProductsRaw[storeId] && additionProductsRaw[storeId][data]) {
              $.each(additionProductsRaw[storeId][data], function(index, value) {
                var html = "<span class='price-piece-nds'>" + $.number((getDefaultPrice(value) * ndsCoef), 2, '.', ' ') + "</span> ??????.";
                if (index == 0) {
                  divSource += html;
                } else {
                  targetDiv += '<div class="js-div-copy price-fixed">' + html + '</div>';
                }
              });
            } else {
              divSource += "<span class='price-piece-nds'>0.00</span> ??????.";
            }
            
            return '<div class="js-div-source price-fixed">' + divSource + '</div><div class="js-target-div">' + targetDiv +'</div>';
          }
        },
        {
          targets: 15,
          data: 'product',
          render: function (data, type, row) {
            var divSource = "";
            var targetDiv = "";
            
            if (additionProductsRaw[storeId] && additionProductsRaw[storeId][data]) {
              $.each(additionProductsRaw[storeId][data], function(index, value) {
                var html = "<span class='price-piece-total'>" + $.number((getDefaultPrice(value) * ndsCoef * value["VOLUME"]), 2, '.', ' ') + "</span> ??????.";
                if (index == 0) {
                  divSource += html;
                } else {
                  targetDiv += '<div class="js-div-copy price-fixed">' + html + '</div>';
                }
              });
            } else {
              divSource += "<span class='price-piece-total'>0.00</span> ??????.";
            }
            
            return '<div class="js-div-source price-fixed">' + divSource + '</div><div class="js-target-div">' + targetDiv +'</div>';
          }
        },
        {
          targets: 16,
          data: 'product',
          render: function (data, type, row) {
            var divSource = '<div class="js-div-source">'
                    + $('<button>', {class: 'btn btn-icon btn-outline-success copy-product', type: 'button'}).append($('<i>', {class: 'la la-plus'}))[0].outerHTML
                    + '</div><div class="js-target-div"></div>';
            var targetDiv = "";
            
            if (additionProductsRaw[storeId] && additionProductsRaw[storeId][data]) {
              $.each(additionProductsRaw[storeId][data], function(index, value) {
                if (index != 0) {
                  targetDiv += '<div class="js-div-copy price-fixed">'
                    + '<button class="btn btn-icon btn-outline-danger delete-row" type="button"><i class="la la-trash"></i></button>'
                    + '</div>';
                }
              });
            }
            
            return '<div class="js-div-source">' + divSource + '</div><div class="js-target-div">' + targetDiv +'</div>';
          }
        }
      ]
    });
    
    dataTable
      .on('xhr.dt', function ( e, settings, json, xhr ) {
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
        
        if (defProduct['ADVERT_50_50'] == 1) {
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
      function(settings, data, dataIndex, row, counter) {
        if (deletedList[row.product]) {
          return false;
        }

        // ???????????? ???? ???????????????????? ?? ?????????? ??????
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
        
        // ???????????????? ????????????
        if (searchText.length >= 3) {
          var product = products[row['product']];
          if (product.NAME.toUpperCase().indexOf(searchText.toUpperCase()) === -1
              && product.CODE.toUpperCase().indexOf(searchText.toUpperCase()) === -1
          ) {
            return false;
          }
        }

        var emptyFilled = true;
        dataTable.cell(dataIndex, 0).nodes().to$().closest('tr').find('.js-td-copy-volume .js-volume').each(function (k, v) {
          if (emptyFilled && $(v).val() > 0) {
            emptyFilled = false;
            return false;
          }
        });

        // ???????????????? ??????????????
        if (brandFilter && brandFilter != products[row.product].BRAND
          || (isSearchNotEmpty === true && emptyFilled)
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

    // ???????????? ???? ??????????????
    var timerSearchBrand;
    $(document).on('change', '#filter-brand', function () {
      preloaderOpen();

      window.clearTimeout(timerSearchBrand);
      timerSearchBrand = setTimeout(function () {
        brandFilter = $('#filter-brand').val();
        dataTable.draw();
        preloaderClose();
      }, 100);
    });

    // ???????????? ???? ??????????????
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
    
    // ???????????? ???? ?????????? ??????
    $(document).on("change", '#filter-price-type', function () {
      var priceTypeId = $(this).val();
      
      if (!priceTypeId) {
        $('#filter-price-agreements').trigger('change');
        return;
      }
      
      var agreementId = priceTypeFilter[priceTypeId]['UF_1C_ID'];
      $('#filter-price-agreements').val(agreementId).trigger('change');
      
    });
    
    // ?????????????????? ?????????? ???? ?????????? ???????????? ?? ????????????
    var timerSearch;
    $(document).on('keyup', '#search_field', function () {

      clearTimeout(timerSearch);

      timerSearch = setTimeout(function () {
        preloaderOpen();
        searchText = $('#search_field').val();
        dataTable.draw();
        preloaderClose();
      }, 700);

    });

    // ?????????????? ????????????
    var timerClearSearch;
    $(document).on('click', '#clear_search_field', function (e) {

      e.preventDefault();
      preloaderOpen();
      window.clearTimeout(timerClearSearch);

      timerClearSearch = setTimeout(function () {
        searchText = '';
        $('#search_field').val('');
        dataTable.draw();
        preloaderClose();
      }, 100);

    });

    // ???????????? ???? ???????????????????? ????????????????
    var timerSearchNotEmpty;
    $(document).on('ifChecked ifUnchecked', '#search-not-empty', function () {

      preloaderOpen();

      window.clearTimeout(timerSearchNotEmpty);
      timerSearchNotEmpty = setTimeout(function () {
        isSearchNotEmpty = $('#search-not-empty').prop('checked');
        dataTable.draw();
        preloaderClose();
      }, 100);

    });

    // ???????????? ???? ?????????????? ????????????????
    var timerPageLen;
    $(document).on("click", '.page-len-button', function (e) {
      var pager = $(this);

      e.preventDefault();
      preloaderOpen();
      window.clearTimeout(timerPageLen);

      timerPageLen = setTimeout(function () {
        if (!pager.hasClass('page-len-selected')) {
          $('.page-len-button').removeClass('page-len-selected');
          pager.addClass('page-len-selected');
          var size = pager.data('size');
          dataTable.page.len(size).draw();
          JSStorage.set(localStorageKeyPageSize, size);
        }
        preloaderClose();
      }, 100);

    });
    
    // ???????????????????? ???????????????????? ????????????????
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
      
    // ???????????????? ????????????
    $(document).on('click', '.copy-product', function() {
      sendTriggerPriceAgreementSelect = false;
      var sourceRowJQ = $(this).closest('tr');
      var sourceRowData = dataTable.row(sourceRowJQ).data();
      var priceTypesKeys = Object.keys(priceTypes[sourceRowData.product]);

      $(sourceRowJQ).find('.js-td-copy').each(function(i, elem) {
        var divSource = $(elem).children('.js-div-source');
        var divTarget = $(elem).children('.js-target-div');
        var cloneElem = $(divSource).clone().addClass('js-div-copy').removeClass('js-div-source');

        // ??????-????
        if ($(elem).hasClass('js-td-copy-volume')) {
          var max = 0;

          if (priceTypesKeys.length == 1) {
            var priceType = priceTypes[sourceRowData.product][priceTypesKeys[0]];
            max = (priceType['VOLUME'] !== undefined && priceType['VOLUME'] > 0) ? priceType['VOLUME'] : volumeDef;
          }
          
          var html = storeType === 'packing'
            ? getVolumeBlock(sourceRowData.product, 'volume', products[sourceRowData.product], 1, sourceRowData.available, BX.message('UNIT_PALLET'), [], max)[0].outerHTML
            : getVolumeBlock(sourceRowData.product, 'volume', products[sourceRowData.product], 0, sourceRowData.available, measures[products[sourceRowData.product].MEASURE].SYMBOL, [], max)[0].outerHTML;

          $(cloneElem).html(html);
        }

        // ??????????
        if ($(elem).hasClass('js-td-copy-pieces')) {
          $(cloneElem).find('.js-pieces').text('0');
        }

        // ?????????? ????????????
        if ($(elem).hasClass('js-td-copy-price-type')) {
          $(cloneElem).html(getPriceTypeSelect(sourceRowData.product, priceTypesKeys.length < 2, [])[0].outerHTML);
        }

        // 50/50
        if ($(elem).hasClass('js-td-copy-50-50')) {
          var ch = getCheckbox(sourceRowData.product, '50-50', 1, 'js-50-50', 0, priceTypesKeys.length > 1);
          $(cloneElem).html(ch[0].outerHTML);
        }

        // ?????????????????????? ????????
        if ($(elem).hasClass('js-td-copy-special-price')) {
          var ch = getCheckbox(sourceRowData.product, 'specialPrice', 1, 'js-special-price', 0, priceTypesKeys.length > 1);
          $(cloneElem).html(ch[0].outerHTML);
        }

        // ???????? ???? ?????????????? ???????????? ?????? ??????
        if ($(elem).hasClass('js-td-copy-price')) {
          $(cloneElem).find('.price-piece').text('0.00');
        }

        // ???????? ???? ?????????????? ???????????? ?? ??????
        if ($(elem).hasClass('js-td-copy-price-nds')) {
          $(cloneElem).find('.price-piece-nds').text('0.00');
        }

        // ?????????? ?????????????????? ???????????? ?? ??????
        if ($(elem).hasClass('js-td-copy-price-total')) {
          $(cloneElem).find('.price-piece-total').text('0.00');
        }

        // ?????????????? ????????????
        if ($(elem).hasClass('js-td-copy-action')) {
          $(cloneElem).html($('<button>', {class: 'btn btn-icon btn-outline-danger', type: 'button'}).append($('<i>', {class: 'la la-trash'}))[0].outerHTML);
          $(cloneElem).children('button').click(function() {
            deleteCopyRow(sourceRowJQ, $(this).parent('div').index());
          });
        }

        $(divTarget).prepend(cloneElem).height(tdHeight * $(divTarget).children().length + 'px');
      });

      $($(sourceRowJQ).find('.price-type-select')[1]).trigger("change");
      dataTable.row(sourceRowJQ).draw(false);
    });
  }
  
  /**
   * ?????????????? ?????????????????????????? ????????????
   * @param {object} row ????????????
   * @param {int} index ????????????, div ?????????????? ?????????? ?????????????? ???? ???????? ?????????????? ????????????
   * @returns {undefined}
   */
  function deleteCopyRow(row, index) {
    $(row).find('.js-td-copy').each(function(i, elem) {
      var targetDiv = $(elem).children('.js-target-div');
      $(targetDiv).height($(targetDiv).height() - tdHeight + 'px');
      $(targetDiv).children('.js-div-copy').eq(index).remove();
    });
    $(row).find("input.js-volume").trigger("change");
    updateTableTotals();
  }

  function initSubmitButtons() {
    var jqXhr;

    $('button.js-submit').on('click', function () {
      if ($(this).hasClass('disabled')) {
        return false;
      }

      preloaderOpen();

      saveType = $(this).val();

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

    function submitBtnDisable(flag) {
      $('button.js-submit').prop('disabled', flag);
    }
    
    function sendData(isUserAgreed, createNewRequest) {
      if (jqXhr || !hasSelectedProducts()) {
        preloaderClose();
        return false;
      }

      submitBtnDisable(true);

      var data = form.serializeArray();
      var counterChosenProducts = 0;
      var dataArt = {};
      
      getActiveProductValues().each(function (index) {
        var obj = $(this).closest('tr');
        
        if (parseFloat($(this).val())) {
          // ???????????????? ???????????? ???? ???????????? ???????????? 1 ??????
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

      if (data.length <= 0 || counterChosenProducts == 0) {
        submitBtnDisable(false);
        preloaderClose();
        return;
      }

      if (isUserAgreed) {
        data.push({name: 'userAgreed', value: true});
      }

      if (createNewRequest) {
        data.push({name: 'createNewRequest', value: true});
      }

      data.push({name: 'action', value: saveType});
      data.push({name: 'rawId', value: rawId});

      jqXhr = $.ajax({
        url: saveType == "saveRaw" ? ajaxUrlRaw : ajaxUrl,
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function (response) {
          if (response.exceededLimit) {
            openExceededLimitDialog(response.products, response.canCreate);
          } else if (response.hasCrossing) {
            openDialog(response.additions, counterChosenProducts);
          } else if(response.error) {
            console.error(response.error);
          } else {
            if (response.additionIds || response.crossAdditions) {
              var additionIds = response.additionIds;
              var crossAdditionsIds = response.crossAdditions;
              location.href = successUrl +
                (additionIds && additionIds.length > 0 ? '&' + jQuery.param({aIds: additionIds}) : '') +
                (crossAdditionsIds && crossAdditionsIds.length > 0 ? '&' + jQuery.param({caIds: crossAdditionsIds}) : '');
            }
            
            if (response.additionId && saveType == "saveRaw") {
              location.href = afterSaveRawUrl.replace('#AGREEMENT_ID#', response.additionId);
            }
          }
        },
        error: function (error) {
          console.error(error);
          return false;
        },
        complete: function () {
          jqXhr = null;
          submitBtnDisable(false);
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

      product.push({name: "product[" + productId + "][" + index + "][id]", value: productId});
      product.push({name: "product[" + productId + "][" + index + "][volume]", value: volume});
      product.push({name: "product[" + productId + "][" + index + "][priceAgreementId]", value: priceAgreementId});
      product.push({name: "product[" + productId + "][" + index + "][priceId]", value: priceId});
      product.push({name: "product[" + productId + "][" + index + "][priceTypeId]", value: priceTypeId});
      product.push({name: "product[" + productId + "][" + index + "][fiftyFyfty]", value: fiftyFyfty ? 1 : 0});
      product.push({name: "product[" + productId + "][" + index + "][specialPrice]", value: specialPrice ? 1 : 0});
      product.push({name: "product[" + productId + "][" + index + "][limit]", value: limit});
      product.push({name: "product[" + productId + "][" + index + "][measure]", value: measure});

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
        '<th>???</th>' +
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

    function openExceededLimitDialog(data, canCreate) {
      let dialogContainer = $('<div id="exceededLimitDialog">');
      let outputTable = BX.message('EXCEEDED_LIMIT_MESSAGE_TEMPLATE');
      let i = 1;
      let product, productData, measureTitle;
      canCreate = canCreate === undefined ? true : canCreate;

      outputTable += '<table autofocus class="table table-bordered table-striped table-hover mt-1 modal-products">' +
        '<thead>' +
        '<th>???</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_NAME_OF_PRODUCT') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_AVAILABLE_VOLUME') + '</th>' +
        '<th>' + BX.message('K_C_ADDITIONAL_MODAL_DECLARED_VOLUME') + '</th>' +
        '</thead>';

      productsError = [];
      for (let productIndex in data) {
        product = products[productIndex];
        productData = data[productIndex];
        productsError[productIndex] = productIndex;
        measureTitle = (storeType === 'packing') ? BX.message('UNIT_PALLET') : measures[productData.measure].SYMBOL;

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
            class: "btn btn-round btn-info" + (canCreate ? '' : ' disabled'),
            'data-toggle' : "tooltip",
            title: canCreate ? '' : BX.message('EXCEEDED_LIMIT_MESSAGE_TEMPLATE_DISABLED_HINT'),
            click: function () {
              if (!canCreate) return false;

              preloaderOpen();
              sendData(true, true);
              $(this).dialog("close");
            }
          },
          {
            text: BX.message('EXCEEDED_LIMIT_MESSAGE_TEMPLATE_RESET_LIMIT'),
            class: "btn btn-round btn-info ml-1",
            click: function () {
              paintProductsError();
              $(this).dialog("close");
            }
          }
        ]
      }, true);

      dialogContainer.dialog('open').css({height: "350px", overflow: "auto"});
      $('.ui-dialog [data-toggle=tooltip]').tooltip();
    }
  }
  
  $.extend(Module, {
    init: function init(options) {
      ajaxUrl = options.ajaxUrl;
      ajaxUrlRaw = options.ajaxUrlRaw;
      measures = options.measures;
      remains5050Access = parseFloat(options.remains5050);
      successUrl = options.successUrl;
      afterSaveUrl = options.afterSaveUrl;
      afterSaveRawUrl = options.afterSaveRawUrl;
      additionProductsRaw = options.additionProducts;
      rawId = options.rawId;

      initInfoTooltips();

      extendContractChangeEvents();
      initDialogs();
      initSelects();
      initDataTable();
      initSubmitButtons();

      $("input#search-not-empty").iCheck({checkboxClass: 'icheckbox_flat-blue'});
    }
  });
})((Additionals = window.Additionals || {}).Raw = Additionals.Raw || {}, ContractSelect, $);
