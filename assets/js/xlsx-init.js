/* jshint browser:true */
/* eslint-env browser */
/* eslint no-use-before-define:0 */
/* global XLSX */
var X = XLSX;
var XW = {
    /* worker message */
    msg: 'xlsx',
    /* worker scripts */
    worker: '/wp-content/themes/brh/node_modules/xlsx/xlsxworker.js',
};

var globalWb = [];

var processWb = (function() {
    var toJson = function toJson(workbook) {
        var result = {};

        workbook.SheetNames.forEach(function(sheetName) {
            var range = XLSX.utils.decode_range(workbook.Sheets[sheetName]['!ref']);
            var headers = getHeaders(range, workbook.Sheets[sheetName]);
            var roa = X.utils.sheet_to_json(workbook.Sheets[sheetName], {
                header: headers,
                range: 1,
            });
            if (roa.length) result[sheetName] = roa;
        });
        return JSON.stringify(result, null, 2);
    };

    var getHeaders = function getHeaders(range, sheet) {
        var headers = [];
        for (var C = range.s.r; C <= range.e.r; ++C) {
            var addr = XLSX.utils.encode_cell({r: range.s.r, c: C});
            var cell = sheet[addr];
            if (!cell) continue;
            headers.push(formatColumnName(cell.v));
        }
        return headers;
    };

    var formatColumnName = function formatColumnName(name) {
        return name.replace(/[\s,\-]/g, '_').toLowerCase();
    };

    return function processWb(wb) {
        globalWb = wb;
        var output = '';
        output = toJson(wb);

        console.log(wb);
        
        jQuery.ajax({
            url: wp.ajaxurl,
            type: 'POST',
            data: {
                action: 'gix_create_posts',
                posts: output,
                _wp_nonce: wp.wp_nonce,
            },
            beforeSend: function() {
                jQuery('#results').addClass('loading');
            },
            complete: function(jqXHR, status) {
                if (status != 'success') {
                    console.log('ajax', new Date(), status, jqXHR);
                }
                jQuery('#results').removeClass('loading');
            },
            success: function(returndData) {
                jQuery('#results').html(returndData);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log(jqXHR, textStatus, errorThrown);
            },
            timeout: 0
        });
    };
})();

var doFile = (function() {
    var rABS = typeof FileReader !== 'undefined' && (
            FileReader.prototype || {}
        ).readAsBinaryString;
    var domrabs = document.getElementsByName('userabs')[0];
    if (!rABS) domrabs.disabled = !(domrabs.checked = false);

    var useWorker = typeof Worker !== 'undefined';
    var domwork = document.getElementsByName('useworker')[0];
    if (!useWorker) domwork.disabled = !(domwork.checked = false);

    var xw = function xw(data, cb) {
        var worker = new Worker(XW.worker);
        worker.onmessage = function(e) {
            switch (e.data.t) {
                case 'ready': break;
                case 'e': console.error(e.data.d); break;
                case XW.msg: cb(JSON.parse(e.data.d)); break;
            }
        };
        worker.postMessage({d: data, b: rABS ? 'binary' : 'array'});
    };

    return function doFile(files) {
        rABS = true;
        useWorker = true;
        var f = files[0];
        var reader = new FileReader();

        reader.onload = function(e) {
            if (typeof console !== 'undefined') {
                console.log('onload', new Date(), rABS, useWorker);
            }
            var data = e.target.result;

            if (!rABS) {
                data = new Uint8Array(data);
            }

            if (useWorker) {
                xw(data, processWb);
            } else {
                processWb(X.read(data, {type: rABS ? 'binary' : 'array'}));
            }
        };

        if (rABS) reader.readAsBinaryString(f);
        else reader.readAsArrayBuffer(f);
    };
})();

(function() {
    var drop = document.getElementById('drop');
    if (!drop.addEventListener) return;

    function handleDrop(e) {
        e.stopPropagation();
        e.preventDefault();
        this.className = this.className.replace('hover', '');
        doFile(e.dataTransfer.files);
    }

    function handleDragover(e) {
        e.stopPropagation();
        e.preventDefault();

        if (this.className.indexOf('hover') == -1)
            this.className += ' hover';

        e.dataTransfer.dropEffect = 'copy';
    }

    function handleLeave(e) {
        this.className = this.className.replace('hover', '');
    }

    drop.addEventListener('dragenter', handleDragover, false);
    drop.addEventListener('dragover', handleDragover, false);
    drop.addEventListener('drop', handleDrop, false);
    drop.addEventListener('dragleave', handleLeave, false);

    var post_select = document.querySelector('#select-post-type');
    post_select.addEventListener('change', function(e, i) {
        console.log(e, i, this.value);
    });
})();

(function() {
    var xlf = document.getElementById('xlf');
    if (!xlf.addEventListener) return;

    function handleFile(e) {
        doFile(e.target.files);
    }

    xlf.addEventListener('change', handleFile, false);
})();
