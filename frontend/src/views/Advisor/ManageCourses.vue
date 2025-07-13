<template>
  <div class="manage-courses">
    <header class="page-header">
      <h1>Manage Courses - Assign Lecturer</h1>
    </header>

    <!-- Add New Course Section -->
    <div class="add-course-section">
      <h2>Add New Course</h2>
      <form @submit.prevent="addCourse" class="course-form">
        <div class="form-group">
          <label for="courseName">Course Name:</label>
          <input 
            type="text" 
            id="courseName" 
            v-model="newCourse.name" 
            required 
            placeholder="Enter course name"
          />
        </div>
        <div class="form-group">
          <label for="courseCode">Course Code:</label>
          <input 
            type="text" 
            id="courseCode" 
            v-model="newCourse.code" 
            required 
            placeholder="e.g., CS101"
          />
        </div>
        <div class="form-group">
          <label for="courseDescription">Description:</label>
          <textarea 
            id="courseDescription" 
            v-model="newCourse.description" 
            placeholder="Course description"
            rows="3"
          ></textarea>
        </div>
        <div class="form-group">
          <label for="credits">Credits:</label>
          <input 
            type="number" 
            id="credits" 
            v-model="newCourse.credits" 
            min="1" 
            max="6" 
            required
          />
        </div>
        <button type="submit" class="btn btn-primary" :disabled="loading">
          {{ loading ? 'Adding...' : 'Add Course' }}
        </button>
      </form>
    </div>

    <!-- Courses List -->
    <div class="courses-section">
      <h2>Existing Courses</h2>
      
      <!-- Loading State -->
      <div v-if="loading" class="loading">
        Loading courses...
      </div>

      <!-- Error State -->
      <div v-if="error" class="error">
        {{ error }}
      </div>

      <!-- Courses Table -->
      <div v-if="!loading && !error" class="courses-table">
        <table>
          <thead>
            <tr>
              <th>Course Code</th>
              <th>Course Name</th>
              <th>Credits</th>
              <th>Current Lecturer</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="course in courses" :key="course.id">
              <td>{{ course.code }}</td>
              <td>{{ course.name }}</td>
              <td>{{ course.credits }}</td>
              <td>
                <span v-if="course.lecturer_name" class="lecturer-assigned">
                  {{ course.lecturer_name }}
                </span>
                <span v-else class="no-lecturer">No lecturer assigned</span>
              </td>
              <td class="actions">
                <button 
                  @click="openAssignModal(course)" 
                  class="btn btn-assign"
                  :disabled="loading"
                >
                  {{ course.lecturer_id ? 'Reassign' : 'Assign' }} Lecturer
                </button>
                <button 
                  @click="editCourse(course)" 
                  class="btn btn-edit"
                  :disabled="loading"
                >
                  Edit
                </button>
                <button 
                  @click="deleteCourse(course.id)" 
                  class="btn btn-delete"
                  :disabled="loading"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Assign Lecturer Modal -->
    <div v-if="showAssignModal" class="modal-overlay" @click="closeAssignModal">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h3>Assign Lecturer to {{ selectedCourse?.name }}</h3>
          <button @click="closeAssignModal" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="assignLecturer">
            <div class="form-group">
              <label for="lecturer">Select Lecturer:</label>
              <select id="lecturer" v-model="selectedLecturerId" required>
                <option value="">-- Choose a lecturer --</option>
                <option 
                  v-for="lecturer in lecturers" 
                  :key="lecturer.id" 
                  :value="lecturer.id"
                >
                  {{ lecturer.name }} ({{ lecturer.email }})
                </option>
              </select>
            </div>
            <div class="modal-actions">
              <button type="button" @click="closeAssignModal" class="btn btn-secondary">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="assignLoading">
                {{ assignLoading ? 'Assigning...' : 'Assign Lecturer' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Course Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click="closeEditModal">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h3>Edit Course</h3>
          <button @click="closeEditModal" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="updateCourse">
            <div class="form-group">
              <label for="editCourseName">Course Name:</label>
              <input 
                type="text" 
                id="editCourseName" 
                v-model="editingCourse.name" 
                required 
              />
            </div>
            <div class="form-group">
              <label for="editCourseCode">Course Code:</label>
              <input 
                type="text" 
                id="editCourseCode" 
                v-model="editingCourse.code" 
                required 
              />
            </div>
            <div class="form-group">
              <label for="editCourseDescription">Description:</label>
              <textarea 
                id="editCourseDescription" 
                v-model="editingCourse.description" 
                rows="3"
              ></textarea>
            </div>
            <div class="form-group">
              <label for="editCredits">Credits:</label>
              <input 
                type="number" 
                id="editCredits" 
                v-model="editingCourse.credits" 
                min="1" 
                max="6" 
                required
              />
            </div>
            <div class="modal-actions">
              <button type="button" @click="closeEditModal" class="btn btn-secondary">
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" :disabled="editLoading">
                {{ editLoading ? 'Updating...' : 'Update Course' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ManageCourses',
  data() {
    return {
      courses: [],
      lecturers: [],
      newCourse: {
        name: '',
        code: '',
        description: '',
        credits: 1
      },
      selectedCourse: null,
      selectedLecturerId: '',
      editingCourse: {
        id: null,
        name: '',
        code: '',
        description: '',
        credits: 1
      },
      showAssignModal: false,
      showEditModal: false,
      loading: false,
      assignLoading: false,
      editLoading: false,
      error: null
    }
  },
  mounted() {
    this.fetchCourses()
    this.fetchLecturers()
  },
  methods: {
    async fetchCourses() {
      try {
        this.loading = true
        this.error = null
        const response = await axios.get('/api/courses')
        this.courses = response.data
      } catch (error) {
        this.error = 'Failed to fetch courses: ' + error.message
        console.error('Error fetching courses:', error)
      } finally {
        this.loading = false
      }
    },

    async fetchLecturers() {
      try {
        const response = await axios.get('/api/lecturers')
        this.lecturers = response.data
      } catch (error) {
        console.error('Error fetching lecturers:', error)
      }
    },

    async addCourse() {
      try {
        this.loading = true
        const response = await axios.post('/api/courses', this.newCourse)
        this.courses.push(response.data)
        this.resetNewCourse()
        alert('Course added successfully!')
      } catch (error) {
        alert('Failed to add course: ' + error.message)
        console.error('Error adding course:', error)
      } finally {
        this.loading = false
      }
    },

    openAssignModal(course) {
      this.selectedCourse = course
      this.selectedLecturerId = course.lecturer_id || ''
      this.showAssignModal = true
    },

    closeAssignModal() {
      this.showAssignModal = false
      this.selectedCourse = null
      this.selectedLecturerId = ''
    },

    async assignLecturer() {
      try {
        this.assignLoading = true
        await axios.put(`/api/courses/${this.selectedCourse.id}/assign-lecturer`, {
          lecturer_id: this.selectedLecturerId
        })
        
        // Update the course in the list
        const courseIndex = this.courses.findIndex(c => c.id === this.selectedCourse.id)
        if (courseIndex !== -1) {
          const lecturer = this.lecturers.find(l => l.id == this.selectedLecturerId)
          this.courses[courseIndex].lecturer_id = this.selectedLecturerId
          this.courses[courseIndex].lecturer_name = lecturer?.name || ''
        }
        
        this.closeAssignModal()
        alert('Lecturer assigned successfully!')
      } catch (error) {
        alert('Failed to assign lecturer: ' + error.message)
        console.error('Error assigning lecturer:', error)
      } finally {
        this.assignLoading = false
      }
    },

    editCourse(course) {
      this.editingCourse = { ...course }
      this.showEditModal = true
    },

    closeEditModal() {
      this.showEditModal = false
      this.editingCourse = {
        id: null,
        name: '',
        code: '',
        description: '',
        credits: 1
      }
    },

    async updateCourse() {
      try {
        this.editLoading = true
        const response = await axios.put(`/api/courses/${this.editingCourse.id}`, {
          name: this.editingCourse.name,
          code: this.editingCourse.code,
          description: this.editingCourse.description,
          credits: this.editingCourse.credits
        })
        
        // Update the course in the list
        const courseIndex = this.courses.findIndex(c => c.id === this.editingCourse.id)
        if (courseIndex !== -1) {
          this.courses[courseIndex] = { ...this.courses[courseIndex], ...response.data }
        }
        
        this.closeEditModal()
        alert('Course updated successfully!')
      } catch (error) {
        alert('Failed to update course: ' + error.message)
        console.error('Error updating course:', error)
      } finally {
        this.editLoading = false
      }
    },

    async deleteCourse(courseId) {
      if (!confirm('Are you sure you want to delete this course?')) {
        return
      }

      try {
        await axios.delete(`/api/courses/${courseId}`)
        this.courses = this.courses.filter(c => c.id !== courseId)
        alert('Course deleted successfully!')
      } catch (error) {
        alert('Failed to delete course: ' + error.message)
        console.error('Error deleting course:', error)
      }
    },

    resetNewCourse() {
      this.newCourse = {
        name: '',
        code: '',
        description: '',
        credits: 1
      }
    }
  }
}
</script>

<style scoped>
.manage-courses {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 30px;
  border-bottom: 2px solid #007bff;
  padding-bottom: 10px;
}

.page-header h1 {
  color: #333;
  font-size: 2.5rem;
  margin: 0;
}

.add-course-section {
  background: #f8f9fa;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 30px;
}

.add-course-section h2 {
  color: #495057;
  margin-bottom: 20px;
}

.course-form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  align-items: end;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: bold;
  margin-bottom: 5px;
  color: #495057;
}

.form-group input,
.form-group textarea,
.form-group select {
  padding: 10px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 14px;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.courses-section h2 {
  color: #495057;
  margin-bottom: 20px;
}

.courses-table {
  overflow-x: auto;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid #dee2e6;
}

th {
  background-color: #f8f9fa;
  font-weight: bold;
  color: #495057;
}

.lecturer-assigned {
  color: #28a745;
  font-weight: bold;
}

.no-lecturer {
  color: #dc3545;
  font-style: italic;
}

.actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  font-weight: bold;
  text-decoration: none;
  display: inline-block;
  transition: background-color 0.3s;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background-color: #007bff;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #0056b3;
}

.btn-secondary {
  background-color: #6c757d;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background-color: #545b62;
}

.btn-assign {
  background-color: #28a745;
  color: white;
}

.btn-assign:hover:not(:disabled) {
  background-color: #1e7e34;
}

.btn-edit {
  background-color: #ffc107;
  color: #212529;
}

.btn-edit:hover:not(:disabled) {
  background-color: #e0a800;
}

.btn-delete {
  background-color: #dc3545;
  color: white;
}

.btn-delete:hover:not(:disabled) {
  background-color: #c82333;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 8px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.modal-header {
  padding: 20px;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  color: #495057;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #6c757d;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-btn:hover {
  color: #495057;
}

.modal-body {
  padding: 20px;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 20px;
}

.loading, .error {
  text-align: center;
  padding: 20px;
  font-size: 16px;
}

.error {
  color: #dc3545;
  background-color: #f8d7da;
  border: 1px solid #f5c6cb;
  border-radius: 4px;
}

@media (max-width: 768px) {
  .course-form {
    grid-template-columns: 1fr;
  }
  
  .actions {
    flex-direction: column;
  }
  
  .btn {
    text-align: center;
  }
  
  table {
    font-size: 12px;
  }
  
  th, td {
    padding: 8px;
  }
}
</style>