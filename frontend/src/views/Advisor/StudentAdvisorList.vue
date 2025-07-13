<template>
  <div>
    <header>
      <h1>Manage Student-Advisor List</h1>
      <div class="student-advisor-container">
        <div class="controls">
          <button @click="addNewAssignment" class="btn-primary">Add New Assignment</button>
          <input 
            v-model="searchTerm" 
            placeholder="Search students or advisors..." 
            class="search-input"
          />
        </div>
        
        <div class="list-container">
          <table class="advisor-table">
            <thead>
              <tr>
                <th>Student Name</th>
                <th>Student ID</th>
                <th>Advisor Name</th>
                <th>Assignment Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="assignment in filteredAssignments" :key="assignment.id">
                <td>{{ assignment.studentName }}</td>
                <td>{{ assignment.studentId }}</td>
                <td>{{ assignment.advisorName }}</td>
                <td>{{ formatDate(assignment.assignmentDate) }}</td>
                <td>
                  <button @click="editAssignment(assignment)" class="btn-edit">Edit</button>
                  <button @click="deleteAssignment(assignment.id)" class="btn-delete">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </header>
  </div>
</template>

<script>

</script>