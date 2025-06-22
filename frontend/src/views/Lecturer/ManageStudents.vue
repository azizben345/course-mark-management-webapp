<template>
  <div>
    <h2>Manage Students</h2>

    <!-- Loop through each course -->
    <div v-for="course in courses" :key="course.course_code">
      <h3>{{ course.course_name }} ({{ course.course_code }})</h3>

      <table>
        <thead>
          <tr>
            <th>Matric No</th>
            <th>Student Name</th>
            <!-- Dynamically create columns for each assessment component -->
            <th v-for="assessment in course.components" :key="assessment.component_id">
              {{ assessment.component_name }}
            </th>
            <th>Final Exam Mark</th>
            <th>Total</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="student in course.students" :key="student.matric_no">
            <td>{{ student.matric_no }}</td>
            <td>{{ student.student_name }}</td>

            <!-- Dynamically display marks for each assessment -->
            <td v-for="assessment in course.components" :key="assessment.component_id">
              {{ getAssessmentMark(student, assessment.component_name) }}
            </td>

            <!-- Final Exam Mark Edit -->
            <td>
              <input 
                v-if="student.isEditingFinalExam"
                v-model="student.newFinalExamMark"
                type="number"
                min="0"
                step="0.01"
                placeholder="Enter Final Exam Mark"
              />
              <span v-else>{{ student.final_exam_mark }}</span>
            </td>

            <td>{{ student.final_total }}</td>

            <td>
              <button v-if="!student.isEditingFinalExam" @click="editStudent(student)">Edit</button>
              <button v-if="student.isEditingFinalExam" @click="saveStudent(student)">Save</button>
              <button @click="deleteStudent(student.enrollment_id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <button @click="goToDashboard" style="margin-bottom: 16px;">Back to Dashboard</button>
  </div>
</template>

<script>
export default {
  name: 'ManageStudents',
  data() {
    return {
      courses: []  // Array to store courses with students and assessments
    };
  },
  created() {
    this.fetchStudents();  // Fetch students when the component is created
  },
  methods: {
    async fetchStudents() {
      const lecturerId = localStorage.getItem('username');  // Get the lecturer's username (which acts as lecturer_id)
      const jwt = localStorage.getItem('jwt');  

      if (!lecturerId || !jwt) {
        console.error("No user found in localStorage");
        return;  // Exit if no lecturer_id is found
      }

      const response = await fetch(`http://localhost:8000/manage-students/${lecturerId}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Content-Type': 'application/json'
        }
      });
      const data = await response.json();

      this.courses = data.courses;  // Assign courses data to the courses array
    },

    getAssessmentMark(student, component_name) {
      // Find the mark for the given assessment component for the student
      const mark = student.marks.find(mark => mark.component_name === component_name);
      return mark ? mark.mark_obtained : 'N/A';  // Return the mark or 'N/A' if not found
    },

    editStudent(student) {
      // Allow the lecturer to edit the final exam mark for the student
      student.isEditingFinalExam = true;
      student.newFinalExamMark = student.final_exam_mark;  // Pre-fill the input with current final exam mark
    },

    async saveStudent(student) {
      // Update the final exam mark and recalculate the total
      const finalExamMark = student.newFinalExamMark;
      const jwt = localStorage.getItem('jwt');  

      // Make an API call to save the updated final exam mark
      const response = await fetch(`http://localhost:8000/students/${student.enrollment_id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${jwt}`,
        },
        body: JSON.stringify({
          final_exam_mark: finalExamMark,
        }),
      });

      const data = await response.json();
      
      if (data.message) {
        // If the update was successful, recalculate the total for this student
        this.updateStudentTotal(student);
        student.isEditingFinalExam = false; // Stop editing
      } else {
        alert("Failed to update the final exam mark.");
      }
    },

    updateStudentTotal(student) {
      // Recalculate total based on CA marks (assumed to be 70%) and final exam (30%)
      let totalAssessmentMarks = 0;
      student.marks.forEach(mark => {
        totalAssessmentMarks += parseFloat(mark.mark_obtained) || 0;  // Summing up marks
      });

      // Assuming that final exam mark is 30% and the CA total is 70%
      student.final_total = (totalAssessmentMarks * 0.7) + (parseFloat(student.final_exam_mark) * 0.3);
    },

    async deleteStudent(enrollment_id) {
      const jwt = localStorage.getItem('jwt');  
      // Send DELETE request to the backend
      const response = await fetch(`http://localhost:8000/students/${enrollment_id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${jwt}`,
        },
      });
      const data = await response.json();

      if (data.message) {
        alert('Student deleted successfully');
        this.fetchStudents();  // Refresh the student list
      }
    },

    goToDashboard() {
      this.$router.push('/dashboard');  // Redirect to the dashboard
    }
  }
};
</script>

<style scoped>
table {
  width: 100%;
  border-collapse: collapse;
}

table th, table td {
  padding: 8px 12px;
  border: 1px solid #ddd;
  text-align: left;
}

table th {
  background-color: #f4f4f4;
}

button {
  padding: 5px 10px;
  cursor: pointer;
  background-color: #2d3748;
  color: white;
  border: none;
  border-radius: 4px;
}

button:hover {
  background-color: #4a5568;
}
</style>
