<script>
(function () {
  var tableBody = document.querySelector('#receiptItemsTable tbody');
  var addBtn = document.getElementById('addMoreItems');
  var maxRows = 10;

  if (!tableBody || !addBtn) {
    return;
  }

  function rowCount() {
    return tableBody.querySelectorAll('tr.item-row').length;
  }

  function updateRemoveButtons() {
    var rows = tableBody.querySelectorAll('tr.item-row');
    rows.forEach(function (row) {
      var btn = row.querySelector('.remove-row');
      if (btn) {
        btn.style.visibility = rows.length > 1 ? 'visible' : 'hidden';
      }
    });
    addBtn.disabled = rows.length >= maxRows;
  }

  function calcTotals() {
    var total = 0;
    tableBody.querySelectorAll('tr.item-row').forEach(function (row) {
      var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
      var rate = parseFloat(row.querySelector('.item-rate').value) || 0;
      var amount = Math.round(qty * rate * 100) / 100;
      row.querySelector('.item-amount').value = amount.toFixed(2);
      total += amount;
    });
    document.getElementById('items_total').value = total.toFixed(2);

    var includeGst = document.getElementById('include_gst').checked;
    var gst = includeGst ? Math.round(total * 0.05 * 100) / 100 : 0;
    var grand = Math.round((total + gst) * 100) / 100;

    document.getElementById('gst_amount').value = gst.toFixed(2);
    document.getElementById('grand_total').value = grand.toFixed(2);
    document.getElementById('gst_amount').disabled = !includeGst;
    document.getElementById('balance_hint').textContent = includeGst
      ? 'Auto-calculated as Grand Total − Advance (editable).'
      : 'Auto-calculated as Total − Advance (editable).';

    var advance = parseFloat(document.getElementById('advance_paid').value);
    var payable = includeGst ? grand : total;
    if (!isNaN(advance)) {
      document.getElementById('balance').value = (Math.round((payable - advance) * 100) / 100).toFixed(2);
    }
  }

  function bindRow(row) {
    row.querySelector('.item-qty').addEventListener('input', calcTotals);
    row.querySelector('.item-rate').addEventListener('input', calcTotals);
    row.querySelector('.remove-row').addEventListener('click', function () {
      if (rowCount() <= 1) return;
      row.remove();
      updateRemoveButtons();
      calcTotals();
    });
  }

  addBtn.addEventListener('click', function () {
    if (rowCount() >= maxRows) return;
    var first = tableBody.querySelector('tr.item-row');
    var clone = first.cloneNode(true);
    clone.querySelectorAll('input').forEach(function (input) {
      input.value = '';
    });
    tableBody.appendChild(clone);
    bindRow(clone);
    updateRemoveButtons();
    calcTotals();
  });

  document.getElementById('advance_paid').addEventListener('input', calcTotals);
  document.getElementById('include_gst').addEventListener('change', calcTotals);
  document.getElementById('mobile').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
  });

  document.getElementById('receiptForm').addEventListener('submit', function () {
    var rows = Array.prototype.slice.call(tableBody.querySelectorAll('tr.item-row'));
    rows.forEach(function (row, index) {
      var name = (row.querySelector('.item-name').value || '').trim();
      var qty = (row.querySelector('.item-qty').value || '').trim();
      var rate = (row.querySelector('.item-rate').value || '').trim();
      if (name === '' && qty === '' && rate === '' && rows.length > 1 && index > 0) {
        row.querySelectorAll('input').forEach(function (input) {
          input.removeAttribute('required');
        });
        row.remove();
      }
    });
  });

  tableBody.querySelectorAll('tr.item-row').forEach(bindRow);
  updateRemoveButtons();
  calcTotals();
})();
</script>
