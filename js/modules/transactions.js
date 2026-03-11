const transactions = {
  init() {
    // Open Trx Modal
    document.getElementById('btn-add-tx').addEventListener('click', () => {
      this.resetForm();
      document.getElementById('tx-date').value = new Date().toISOString().split('T')[0];
      document.getElementById('tx-modal-title').innerText = 'New Transaction';
      ui.openModal('tx-modal');
    });

    // Submit Trx
    document.getElementById('tx-form').addEventListener('submit', async (e) => {
      e.preventDefault();

      const id = document.getElementById('tx-id').value;
      const data = {
        type: document.getElementById('tx-type').value,
        date: document.getElementById('tx-date').value,
        amount: document.getElementById('tx-amount').value,
        category: document.getElementById('tx-category').value,
        note: document.getElementById('tx-note').value
      };

      try {
        if (id) {
          await api.put(`/transactions/${id}`, data);
          ui.showToast('Transaction updated', 'success');
        } else {
          await api.post('/transactions', data);
          ui.showToast('Transaction saved', 'success');
        }

        ui.closeModal('tx-modal');
        this.loadTransactions();
      } catch (error) {
        ui.showToast(error.message, 'error');
      }
    });

    // Import Excel
    document.getElementById('btn-import-excel').addEventListener('click', () => {
      document.getElementById('import-form').reset();
      document.getElementById('import-preview-section').style.display = 'none';
      ui.openModal('import-modal');
    });

    document.getElementById('link-template').addEventListener('click', (e) => {
      e.preventDefault();
      const token = localStorage.getItem('pms_token');
      // Direct download link with token
      window.location.href = `${API_URL}/import/template?token=${token}`;
    });

    document.getElementById('import-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fileInput = document.getElementById('import-file');
      if (fileInput.files.length === 0) {
        ui.showToast('Please select a file', 'error');
        return;
      }

      const formData = new FormData();
      formData.append('file', fileInput.files[0]);

      try {
        const response = await fetch(`${API_URL}/import/upload`, {
          method: 'POST',
          headers: { 'Authorization': `Bearer ${localStorage.getItem('pms_token')}` },
          body: formData
        });

        const result = await response.json();

        if (response.ok) {
          this.showImportPreview(result.data);
        } else {
          throw new Error(result.message);
        }
      } catch (error) {
        ui.showToast(error.message, 'error');
      }
    });

    document.getElementById('btn-confirm-import').addEventListener('click', async () => {
      try {
        const res = await api.post('/import/save', {
          transactions: this.previewData
        });
        ui.showToast(res.message, 'success');
        ui.closeModal('import-modal');
        this.loadTransactions();
      } catch (error) {
        ui.showToast(error.message, 'error');
      }
    });

    // Reports Handlers
    document.getElementById('btn-pdf').addEventListener('click', () => this.exportReport('pdf'));
    document.getElementById('btn-excel').addEventListener('click', () => this.exportReport('excel'));
  },

  previewData: [],

  showImportPreview(data) {
    this.previewData = data;
    const tbody = document.querySelector('#import-preview-table tbody');
    tbody.innerHTML = '';

    data.forEach(row => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
                <td>${row.date}</td>
                <td><span class="badge ${row.type}">${row.type}</span></td>
                <td>$${row.amount}</td>
                <td>${row.category}</td>
            `;
      tbody.appendChild(tr);
    });

    document.getElementById('import-preview-section').style.display = 'block';
  },

  resetForm() {
    document.getElementById('tx-id').value = '';
    document.getElementById('tx-form').reset();
  },

  async loadTransactions() {
    try {
      const data = await api.get('/transactions');
      const tbody = document.querySelector('#transactions-table tbody');
      tbody.innerHTML = '';

      let totalIncome = 0;
      let totalExpense = 0;

      if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No transactions found.</td></tr>';
      } else {
        data.forEach(tx => {
          const amount = parseFloat(tx.amount);
          if (tx.type === 'income') totalIncome += amount;
          else totalExpense += amount;

          const row = document.createElement('tr');
          row.innerHTML = `
                        <td>${tx.date}</td>
                        <td><span class="badge ${tx.type}">${tx.type}</span></td>
                        <td>${tx.category}</td>
                        <td style="font-weight: 600">$${amount.toFixed(2)}</td>
                        <td>${tx.note || '-'}</td>
                        <td>
                            <button class="action-btn edit" onclick="transactions.editTx(${tx.id})"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button class="action-btn delete" onclick="transactions.deleteTx(${tx.id})"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    `;
          tbody.appendChild(row);
        });
      }

      // Update dashboard widgets
      document.getElementById('widget-income-total').innerText = `$${totalIncome.toFixed(2)}`;
      document.getElementById('widget-expense-total').innerText = `$${totalExpense.toFixed(2)}`;

    } catch (error) {
      ui.showToast('Failed to load transactions', 'error');
    }
  },

  async editTx(id) {
    try {
      const tx = await api.get(`/transactions/${id}`);
      document.getElementById('tx-id').value = tx.id;
      document.getElementById('tx-type').value = tx.type;
      document.getElementById('tx-date').value = tx.date;
      document.getElementById('tx-amount').value = tx.amount;
      document.getElementById('tx-category').value = tx.category;
      document.getElementById('tx-note').value = tx.note;

      document.getElementById('tx-modal-title').innerText = 'Edit Transaction';
      ui.openModal('tx-modal');
    } catch (error) {
      ui.showToast('Failed to load transaction details', 'error');
    }
  },

  async deleteTx(id) {
    if (confirm('Are you sure you want to delete this transaction?')) {
      try {
        await api.delete(`/transactions/${id}`);
        ui.showToast('Transaction deleted', 'success');
        this.loadTransactions();
      } catch (error) {
        ui.showToast(error.message, 'error');
      }
    }
  },

  exportReport(format) {
    const start = document.getElementById('rep-start').value;
    const end = document.getElementById('rep-end').value;
    const type = document.getElementById('rep-type').value;

    let query = `?token=${localStorage.getItem('pms_token')}`;
    if (start) query += `&start=${start}`;
    if (end) query += `&end=${end}`;
    if (type) query += `&type=${type}`;

    window.location.href = `${API_URL}/reports/${format}${query}`;
  }
};

window.transactions = transactions;
