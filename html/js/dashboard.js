/* Dashboard JS extracted from html/pages/dashboard.inc.php
 * Provides initialization and handlers for the grid, widgets, and actions.
 */

function ObserviumDashboardInit(options) {
  // options: { cellHeight, hMargin, vMargin, isEditing, dashId, slug, initialGrid, requesttoken, widgetDefaults, redirectUrl }
  var gridObj;

  function isNumber(n) { return !isNaN(parseFloat(n)) && isFinite(n); }

  // Determine current dashboard id reliably in view and edit modes
  function currentDashId() {
    var v = $('#dash_id').val();
    if (v && v !== 'undefined') { return v; }
    if (options && isNumber(options.dashId)) { return options.dashId; }
    var sel = $('#dashboard_picker').val();
    if (sel && sel !== 'undefined') { return sel; }
    return null;
  }

  function ajaxWithToken(url, data, success) {
    data = data || {};
    if (options.requesttoken) { data.requesttoken = options.requesttoken; }
    return $.ajax({ type: 'POST', url: url, data: $.param(data), cache: false, success: success, error: function(xhr, status, err){
      var message = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : (xhr && xhr.responseText ? xhr.responseText : (status + ': ' + err));
      showAlert('Request failed: ' + message, 'danger');
      if (window.console) { console.error('AJAX error:', url, status, err); }
    }});
  }

  function showAlert(msg, type) {
    type = type || 'warning';
    var $c = $('#dashboard-alerts');
    if (!$c.length) { $('body').prepend('<div id=\"dashboard-alerts\"></div>'); $c = $('#dashboard-alerts'); }
    var $a = $('<div class=\"alert alert-' + type + '\" role=\"alert\" style=\"margin-bottom:8px;\">' + $('<div>').text(msg).html() + '</div>');
    $c.prepend($a);
    setTimeout(function(){ $a.fadeOut(300, function(){ $(this).remove(); }); }, 5000);
  }

  $(function () {
    var gridstackOptions = {
      cellHeight: options.cellHeight,
      horizontalMargin: options.hMargin,
      verticalMargin: options.vMargin,
      resizable: {
        autoHide: true,
        handles: options.isEditing ? 'se, sw' : 'none'
      },
      draggable: {
        handle: '.drag-handle'
      }
    };
    $('.grid-stack').gridstack(gridstackOptions);

    gridObj = $('.grid-stack').data('gridstack');

    ///////////////
    // LOAD GRID //
    ///////////////
    function loadGrid() {
      gridObj.removeAll();
      var items = GridStackUI.Utils.sort(options.initialGrid || []);
      _.each(items, function (node) {
        node.autoposition = null;
        drawWidget(node);
      });
      return false;
    }

    ///////////////
    // SAVE GRID //
    ///////////////
    function saveGrid() {
      var current = _.map($('.grid-stack > .grid-stack-item:visible'), function (el) {
        el = $(el);
        var node = el.data('_gridstack_node');
        return { x: node.x, y: node.y, width: node.width, height: node.height, id: el.attr('data-gs-id') };
      });
      $('#saved-data').val(JSON.stringify(current, null, '    '));

      ajaxWithToken('ajax/actions.php', { action: 'save_grid', grid: current });
      return false;
    }

    // Expose for other handlers
    window.ObserviumDashboardSave = saveGrid;

    /////////////////////
    // DRAW THE WIDGET //
    /////////////////////
    function drawWidget(node) {
      var controls = '';
      var widgetType = node.type || (node.widget_type ? node.widget_type : '');
      var meta = (options.widgetDefaults && widgetType && options.widgetDefaults[widgetType]) ? options.widgetDefaults[widgetType] : null;

      if (options.isEditing) {
        controls = '<div class="hover-show" style="z-index: 1000; position: absolute; top:0px; right: 10px; padding: 2px 10px; padding-right: 0px; border-bottom-left-radius: 4px; border: 1px solid #e5e5e5; border-right: none; border-top: none; background: white;">' +
          '  <i style="cursor: pointer; margin: 7px;" class="sprite-refresh" onclick="refreshWidget(' + node.id + ')"></i>' +
          '  <i style="cursor: pointer; margin: 7px;" class="sprite-tools" onclick="configWidget(' + node.id + ')"></i></i>' +
          '  <i style="cursor: no-drop; margin: 7px;" class="sprite-cancel" onclick="deleteWidget(' + node.id + ')"></i>' +
          '  <i style="cursor: move; margin: 7px; margin-right: 20px" class="sprite-move drag-handle"></i>' +
          '</div>';
      }

      var $wrapper = $('<div>').attr('data-widget-type', widgetType || '');
      var $content = $('<div>').attr('id', 'widget-' + node.id).addClass('grid-stack-item-content');
      $wrapper.append($content);
      if (controls) {
        $wrapper.append($(controls));
      }

      gridObj.addWidget($wrapper,
        node.x, node.y,
        node.width, node.height,
        node.autoposition,
        meta && typeof meta.minW !== 'undefined' ? meta.minW : null,
        meta && typeof meta.maxW !== 'undefined' ? meta.maxW : null,
        meta && typeof meta.minH !== 'undefined' ? meta.minH : null,
        meta && typeof meta.maxH !== 'undefined' ? meta.maxH : null,
        node.id);
    }

    ////////////////
    // ADD WIDGET //
    ////////////////
    window.addNewWidget = function (type, dash_id) {
      ajaxWithToken('ajax/actions.php', { action: 'add_widget', widget_type: type, dash_id: dash_id }, function (response) {
        if (isNumber(response.id)) {
          var def = (options.widgetDefaults && options.widgetDefaults[type]) ? options.widgetDefaults[type] : { w: 4, h: 3 };
          var node = { width: def.w, height: def.h, autoposition: true, id: response.id, type: type };
          drawWidget(node);
          saveGrid();
          refreshAllWidgets(response.id);
        }
      });
      return false;
    };

    /////////////////////////
    // Refresh All Widgets //
    /////////////////////////
    window.refreshAllWidgets = function () {
      $('.grid-stack-item').each(function () {
        refreshWidget($(this).attr('data-gs-id'));
      });
    };

    window.refreshAllUpdatableWidgets = function () {
      $('.grid-stack-item').each(function () {
        if (!$(this).children('div').children('div').hasClass('do-not-update') && !$(this).is(':hover')) {
          refreshWidget($(this).attr('data-gs-id'));
        }
      });
    };

    window.refreshAllUpdatableImages = function () {
      var pt = /\&nocache=\d+/;
      $('.image-refresh').each(function () {
        if (this.src) {
          pt.test(this.src) ? $(this).attr('src', this.src.replace(pt, '&nocache=' + Date.now())) : $(this).attr('src', this.src + '&nocache=' + Date.now());
        }
        if (this.srcset) {
          pt.test(this.srcset) ? $(this).attr('srcset', this.srcset.replace(pt, '&nocache=' + Date.now())) : $(this).attr('srcset', this.srcset.replace(/\ /, '&nocache=' + Date.now() + ' '));
        }
      });
    };

    ///////////////////////////
    // Refresh single widget //
    ///////////////////////////
    window.refreshWidget = function (id) {
      var div = $('#widget-' + id);
      var params = { width: div.innerWidth(), height: div.innerHeight(), id: id };
      ajaxWithToken('ajax/widget.php', params, function (response) { div.html(response); });
    };

    window.deleteWidget = function (id) {
      var el = $(".grid-stack-item[data-gs-id='" + id + "']");
      ajaxWithToken('ajax/actions.php', { action: 'del_widget', widget_id: id }, function (response) {
        gridObj.removeWidget(el);
      });
    };

    window.configWidget = function (id) {
      ajaxWithToken('ajax/actions.php', { action: 'edit_widget', widget_id: id }, function (response) {
        $('#config-modal-body').html(response);

        // Initialize form controls after AJAX content is loaded
        setTimeout(function() {
          $('#config-modal-body .selectpicker').selectpicker();
          // Refresh selectpickers to ensure values are properly displayed
          $('#config-modal-body .selectpicker').selectpicker('refresh');
          $('#config-modal-body [data-toggle="tooltip"]').tooltip();
        }, 100);

        $('#config-modal').modal({ show: true });
      });
    };

    window.saveWidgetConfig = function (widget_id) {
      var config = {};

      // Collect all widget config fields
      $('#config-modal-body input[data-field], #config-modal-body select[data-field]').each(function() {
        var $field = $(this);
        var fieldName = $field.attr('data-field');
        var fieldType = $field.attr('data-type');
        var value = $field.val();

        // Handle multiselect arrays
        if (fieldType === 'multiselect' && Array.isArray(value)) {
          config[fieldName] = value;
        } else if (fieldType === 'checkbox') {
          config[fieldName] = $field.prop('checked') ? 'yes' : 'no';
        } else {
          config[fieldName] = value;
        }
      });

      // Send the configuration update
      ajaxWithToken('ajax/actions.php', {
        action: 'update_widget_config',
        widget_id: widget_id,
        config: config
      }, function (response) {
        if (response.status === 'ok') {
          $('#config-modal').modal('hide');
          // Refresh the widget if needed
          location.reload(); // Simple refresh for now
        } else {
          alert('Error saving configuration: ' + (response.message || 'Unknown error'));
        }
      });
    };

    window.dashDelete = function () {
      var dash_id = $('#dash_id').val();
      ajaxWithToken('ajax/actions.php', { action: 'dash_delete', dash_id: dash_id }, function (json) {
        if (json.status === 'ok') {
          var url = options.redirectUrl || 'dashboard/';
          window.setTimeout(function(){ window.location.href = url; }, 500);
        }
      });
    };

    // Set dashboard visibility (public/private)
    window.dashSetPublic = function () {
      var dash_id = $('#dash_id').val();
      var is_public = $('#dash_public').is(':checked');
      ajaxWithToken('ajax/actions.php', { action: 'dash_visibility', dash_id: dash_id, is_public: is_public ? 1 : 0 }, function (json) {
        // alerts handled centrally
      });
    };

    // Set default dashboard for current user
    window.dashSetDefault = function () {
      var dash_id = currentDashId();
      ajaxWithToken('ajax/actions.php', { action: 'dash_set_default', dash_id: dash_id }, function (json) {
        if (json.status === 'ok') { showAlert('Default dashboard updated.', 'success'); }
        else if (json && json.message) { showAlert(json.message, 'warning'); }
      });
    };
    // Export current dashboard to a JSON file
    window.dashExport = function(){
      var dash_id = currentDashId();
      if (!dash_id) { showAlert('Unable to determine dashboard id.', 'warning'); return; }
      ajaxWithToken('ajax/actions.php', { action: 'dash_export', dash_id: dash_id }, function (json) {
        if (json.status === 'ok' && json.payload) {
          var filenameSlug = null;
          try {
            var p = JSON.parse(json.payload);
            if (p && p.slug) { filenameSlug = p.slug; }
          } catch(e) { /* ignore */ }
          if (!filenameSlug && options && options.slug) { filenameSlug = options.slug; }
          var safeSlug = filenameSlug ? String(filenameSlug).toLowerCase().replace(/[^a-z0-9\-]+/g, '-') : null;
          var downloadName = 'dashboard-' + (safeSlug || dash_id || 'export') + '.json';
          var blob = new Blob([json.payload], {type: 'application/json'});
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = downloadName;
          document.body.appendChild(a); a.click(); document.body.removeChild(a);
        } else if (json && json.message) {
          showAlert(json.message, 'warning');
        } else {
          showAlert('Export failed.', 'warning');
        }
      });
    };
    // Import dashboard JSON from user and create a new dashboard
    window.dashImport = function(){
      var input = $('<input type="file" accept="application/json" style="display:none;" />');
      $('body').append(input);
      input.on('change', function(){
        var file = this.files[0];
        if (!file) { input.remove(); return; }
        var reader = new FileReader();
        reader.onload = function(e){
          var payload = e.target.result;
          ajaxWithToken('ajax/actions.php', { action: 'dash_import', payload: payload }, function(json){
            if (json.status === 'ok' && (json.slug || json.id)) {
              var target = '?page=dashboard&dash=' + encodeURIComponent(json.slug || json.id) + '&edit=yes';
              window.location.href = target;
            } else if (json && json.message) {
              showAlert(json.message, 'warning');
            } else {
              showAlert('Import failed.', 'warning');
            }
          });
        };
        reader.readAsText(file);
        setTimeout(function(){ input.remove(); }, 1000);
      }).click();
    };

    // Update dashboard description
    $(document).on('change', '#dash_descr', function () {
      var dash_id = $('#dash_id').val();
      var descr = $('#dash_descr').val();
      ajaxWithToken('ajax/actions.php', { action: 'dash_update_descr', dash_id: dash_id, descr: descr });
    });

    // Clone current dashboard into a private copy for this user
    window.dashClone = function () {
      var dash_id = currentDashId();
      if (!dash_id) { showAlert('Unable to determine dashboard id.', 'warning'); return; }
      ajaxWithToken('ajax/actions.php', { action: 'dash_clone', dash_id: dash_id }, function (json) {
        if (json.status === 'ok' && (json.slug || json.id)) {
          var target = '?page=dashboard&dash=' + encodeURIComponent(json.slug || json.id) + '&edit=yes';
          window.location.href = target;
        } else if (json && json.message) {
          showAlert(json.message, 'warning');
        } else {
          showAlert('Clone failed.', 'warning');
        }
      });
    };

    // Dashboard picker change
    $(document).on('change', '#dashboard_picker', function(){
      var did = $(this).val();
      if (did) { window.location.href = '?page=dashboard&dash=' + did; }
    });

    // Enhanced reorder UI with better visual feedback
    window.dashReorderToggle = function(){
      var c = $('#dash-reorder');
      if (!c.length) { return; }
      c.slideToggle(200);
      
      try {
        if (!c.data('sortable-init-my')) {
          if ($('#dash-reorder-my-list').length) {
            $('#dash-reorder-my-list').sortable({ 
              handle: '.drag-handle',
              tolerance: 'pointer',
              placeholder: 'alert alert-warning',
              start: function(event, ui) {
                ui.placeholder.text('Drop here');
                ui.item.addClass('dragging');
              },
              stop: function(event, ui) {
                ui.item.removeClass('dragging');
                dashReorderSave('my');
              }
            });
          }
          c.data('sortable-init-my', true);
        }
        if (!c.data('sortable-init-public')) {
          if ($('#dash-reorder-public-list').length) {
            $('#dash-reorder-public-list').sortable({ 
              handle: '.drag-handle',
              tolerance: 'pointer', 
              placeholder: 'alert alert-warning',
              start: function(event, ui) {
                ui.placeholder.text('Drop here');
                ui.item.addClass('dragging');
              },
              stop: function(event, ui) {
                ui.item.removeClass('dragging');
                dashReorderSave('public');
              }
            });
          }
          c.data('sortable-init-public', true);
        }
      } catch(e) {
        console.error('Failed to initialize sortable:', e);
      }
    };
    
    window.dashReorderSave = function(scope){
      var listId = (scope === 'public') ? '#dash-reorder-public-list' : '#dash-reorder-my-list';
      if (!$(listId).length) { 
        showAlert('Nothing to reorder for ' + (scope || 'my') + '.', 'warning'); 
        return; 
      }
      var order = [];
      $(listId).children('.alert').each(function(){ 
        var dash = $(this).data('dash');
        if (dash) order.push(dash); 
      });
      if (order.length === 0) { return; }
      
      ajaxWithToken('ajax/actions.php', { action: 'dash_reorder', order: order, scope: scope || 'my' }, function(resp){
        if (resp && resp.status === 'ok') { 
          showAlert('Dashboard order saved automatically.', 'success'); 
        } else { 
          showAlert('Unable to save order.', 'warning'); 
        }
      });
    };

    // Actions hooks
    $("#dashboard_editor").submit(function (event) {
      if ($('#widget_type').val()) { addNewWidget($('#widget_type').val(), $('#dash_id').val()); }
      event.preventDefault();
    });

    $("#dash_name").change(function () {
      var dash_id = $('#dash_id').val();
      var dash_name = $('#dash_name').val();
      ajaxWithToken('ajax/actions.php', { action: 'dash_rename', dash_id: dash_id, dash_name: dash_name });
    });

    // Widget help text now shown as select subtext; no dynamic help needed

    // Init grid and first paint
    loadGrid();
    window.refreshAllWidgets();

    // Debounced save on changes; still refresh changed widgets immediately
    var saveGridDebounced = _.debounce(function(){ saveGrid(); }, 300);
    $('.grid-stack').on('change', function (event, items) {
      if (items && items.forEach) {
        items.forEach(function (item) { refreshWidget(item.el.attr('data-gs-id')); });
      }
      saveGridDebounced();
    });
    $('.grid-stack').on('dragstop resizestop', function () { saveGridDebounced(); });

    // Periodic refresh (skip when tab is hidden to reduce load)
    setInterval(function () {
      if (document.hidden) { return; }
      window.refreshAllUpdatableWidgets();
    }, 20000);
    setInterval(function () {
      if (document.hidden) { return; }
      window.refreshAllUpdatableImages();
    }, 15000);

    // Container resize refresh (debounced)
    var resizeDebounced = _.debounce(function(){ window.refreshAllWidgets(); }, 300);
    new ResizeSensor(jQuery('#main_container'), function () { resizeDebounced(); });
  });
}
