<template>
  <div>
    <h2 class="text-xl font-bold mb-4">Add Course</h2>
    <form @submit.prevent="submitCourse" class="space-y-4">
      <input v-model="course.course_code" placeholder="Course Code" required class="input" />
      <input v-model="course.course_name" placeholder="Course Name" required class="input" />

      <select v-model="course.lecturer_id" required class="input">
        <option disabled value="">Select Lecturer</option>
        <option v-for="lecturer in lecturers" :key="lecturer.lecturer_id" :value="lecturer.lecturer_id">
          {{ lecturer.full_name }}
        </option>
      </select>

      <button type="submit" class="btn">Add Course</button>
    </form>
  </div>
</template>

<script>
export default {
  data() {
    return {
      course: {
        course_code: '',
        course_name: '',
        lecturer_id: ''
      },
      lecturers: []
    };
  },
  created() {
    // Fetch lecturers on page load using fetch API
    fetch('http://localhost:8000/api/lecturers')
      .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
      })
      .then(data => {
        this.lecturers = data;
      })
      .catch(err => {
        alert('Failed to load lecturers: ' + err.message);
      });
  },
  methods: {
    submitCourse() {
      fetch('http://localhost:8000/api/courses', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(this.course)
      })
        .then(res => {
          if (!res.ok) return res.json().then(err => { throw err; });
          alert('✅ Course added!');
          this.course = { course_code: '', course_name: '', lecturer_id: '' };
        })
        .catch(err => {
          alert('❌ Error: ' + (err.error || err.message));
        });
    }
  }
};
</script>

<style scoped>
.input {
  display: block;
  width: 100%;
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
}
.btn {
  background: #4f46e5;
  color: white;
  padding: 10px 16px;
  border: none;
  border-radius: 4px;
}
</style>
