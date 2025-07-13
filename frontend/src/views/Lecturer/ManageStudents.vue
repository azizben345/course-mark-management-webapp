<template>
  <div>
    <h2>Manage Students</h2>

    <!-- Loop through each course -->
    <div v-for="course in courses" :key="course.course_code">
      <h3>{{ course.course_name }} ({{ course.course_code }})</h3>

      <button @click="exportCourseToCSV(course)" style="margin-bottom: 8px;">Export to CSV</button>

      <table>
        <thead>
          <tr>
            <th>Matric No</th>
            <th>Student Name</th>
            <th v-for="assessment in course.components" :key="assessment.component_id">
                {{ assessment.component_name }} ({{ assessment.max_mark }})
            </th>
            <th>Final Exam Mark</th>
            <th>Total</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="student in course.students" :key="student.matric_no">
            <td>{{ student.matric_no }}</td>
            <td>{{ student.full_name }}</td>

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
              <button 
                v-if="student.isEditingFinalExam" 
                @click="student.isEditingFinalExam = false"
                style="margin-left: 8px; background-color: #e53e3e;"
              >
                Cancel
              </button>
            </td>

            <td>{{ student.final_total }}</td>

            <td>
              <button v-if="!student.isEditingFinalExam" @click="editStudent(student)">Edit Final</button>
              <button v-if="student.isEditingFinalExam" @click="saveStudent(student)">Save</button>
              <button @click="deleteStudent(student.enrollment_id)">Unenroll</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <button @click="$router.push('/dashboard')" style="margin-bottom: 16px;">Back to Dashboard</button>
  </div>
</template>

<script>
export default {
  name: 'ManageStudents',
  data() {
    return {
      courses: []  // array to store courses with students and assessments
    };
  },
  created() {
    this.fetchStudents();  
  },
  methods: {
    async fetchStudents() {
      const lecturerId = 'LC002';//(localStorage.getItem('user_info') || {}).username;  // Get the lecturer's username (which acts as lecturer_id)
      const jwt = localStorage.getItem('jwt_token');

      const response = await fetch(`http://localhost:8000/manage-students/${lecturerId}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${jwt}`,
          'Content-Type': 'application/json'
        }
      });
      const data = await response.json();

      this.courses = data.courses;  
    },

    getAssessmentMark(student, component_name) {
      
      const mark = student.marks.find(mark => mark.component_name === component_name);
      return mark ? mark.mark_obtained : 'N/A';  
    },

    editStudent(student) {
      
      student.isEditingFinalExam = true;
      student.newFinalExamMark = student.final_exam_mark;  // pre-fill the input with current final exam mark
    },

    async saveStudent(student) {
      
      const finalExamMark = student.newFinalExamMark;
      const jwt = localStorage.getItem('jwt_token');  

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
        
        await this.fetchStudents();
        student.isEditingFinalExam = false;
      } else {
        alert("Failed to update the final exam mark.");
      }
    },

    async deleteStudent(enrollment_id) {
      if (!confirm('Are you sure you want to unenroll this student?')) {
      return;
      }
      const jwt = localStorage.getItem('jwt_token');  
      
      const response = await fetch(`http://localhost:8000/students/${enrollment_id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${jwt}`,
      },
      });
      const data = await response.json();

      if (data.message) {
      alert('Student deleted successfully');
      this.fetchStudents();  // refresh the student list
      }
    },

    // Generate and download the CSV file for the selected course
    exportCourseToCSV(course) {
      const header = ['Matric No', 'Student Name'];
      
      course.components.forEach(assessment => {
        header.push(`${assessment.component_name} (Max: ${assessment.max_mark})`);
      });
      header.push('Final Exam Mark', 'Total');

      // array of data for each student in the course
      const rows = course.students.map(student => {
        const row = [
          student.matric_no, 
          student.full_name,
          ...course.components.map(assessment => {
            const mark = student.marks.find(mark => mark.component_name === assessment.component_name);
            return mark ? mark.mark_obtained : 'N/A';  // If no mark, show 'N/A'
          }),
          student.final_exam_mark,
          student.final_total
        ];
        return row;
      });

      // Convert the header and rows into CSV format
      const csvContent = [
        header.join(','),  // Convert header to a CSV string
        ...rows.map(row => row.join(','))  // Convert rows to CSV string
      ].join('\n');  // Join rows with newlines

      // Create a Blob for the CSV content and generate a download link
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `${course.course_name}_${course.course_code}_students.csv`; // File name
      link.click();
    },

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
