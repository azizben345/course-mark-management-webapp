<template>
  <div>
    <header>
      <h1>Meeting Records</h1>
      <div class="meeting-controls">
        <button @click="showCreateForm = true" class="btn-primary">New Meeting</button>
        <input v-model="searchTerm" placeholder="Search meetings..." class="search-input">
      </div>

      <!-- Create/Edit Meeting Form -->
      <div v-if="showCreateForm" class="modal-overlay">
        <div class="modal">
          <h3>{{ editingMeeting ? 'Edit' : 'Create' }} Meeting Record</h3>
          <form @submit.prevent="saveMeeting">
            <div class="form-group">
              <label>Student:</label>
              <select v-model="currentMeeting.studentId" required>
                <option value="">Select Student</option>
                <option v-for="student in students" :key="student.id" :value="student.id">
                  {{ student.name }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Date:</label>
              <input type="datetime-local" v-model="currentMeeting.date" required>
            </div>
            <div class="form-group">
              <label>Notes:</label>
              <textarea v-model="currentMeeting.notes" rows="5" placeholder="Meeting notes..."></textarea>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary">Save</button>
              <button type="button" @click="cancelForm" class="btn-secondary">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Meetings List -->
      <div class="meetings-list">
        <div v-for="meeting in filteredMeetings" :key="meeting.id" class="meeting-card">
          <div class="meeting-header">
            <h4>{{ getStudentName(meeting.studentId) }}</h4>
            <span class="meeting-date">{{ formatDate(meeting.date) }}</span>
          </div>
          <p class="meeting-notes">{{ meeting.notes }}</p>
          <div class="meeting-actions">
            <button @click="editMeeting(meeting)" class="btn-edit">Edit</button>
            <button @click="deleteMeeting(meeting.id)" class="btn-delete">Delete</button>
          </div>
        </div>
      </div>
    </header>
  </div>
</template>

<script>

</script>