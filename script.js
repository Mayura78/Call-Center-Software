// script.js
document.addEventListener('DOMContentLoaded', function(){
  // Edit button - populate edit modal
  document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', function(){
      const id = this.dataset.id;
      const name = this.dataset.name;
      const number = this.dataset.number;
      const email = this.dataset.email;
      const shift = this.dataset.shift;
      const status = this.dataset.status || 'Offline';
      const notes = this.dataset.notes || '';

      document.getElementById('edit_agent_id').value = id;
      document.getElementById('edit_agent_name').value = name;
      document.getElementById('edit_agent_number').value = number;
      document.getElementById('edit_email').value = email;
      document.getElementById('edit_shift').value = shift;
      document.getElementById('edit_status').value = status;
      document.getElementById('edit_notes').value = notes;

      var editModal = new bootstrap.Modal(document.getElementById('editModal'));
      editModal.show();
    });
  });
});