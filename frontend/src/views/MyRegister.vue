<template>
  <div>
    <div class="register-container">
      <h2>Register</h2>
      <form @submit.prevent="register">
        <!-- Common Fields -->
        <div class="form-row">
          <label for="username">Username:</label>
          <input v-model="form.username" type="text" id="username" required />
        </div>

        <div class="form-row">
          <label for="password">Password:</label>
          <input v-model="form.password" type="password" id="password" required />
        </div>

        <div class="form-row">
          <label for="confirmPassword">Confirm Password:</label>
          <input v-model="form.confirmPassword" type="password" id="confirmPassword" required />
        </div>

        <div class="form-row">
          <label for="role">Role:</label>
          <select v-model="form.role" id="role" required>
            <option :value="null" disabled>-- Please select a role --</option> <!-- Added default null option -->
            <option value="student">Student</option>
            <option value="lecturer">Lecturer</option>
            <option value="advisor">Advisor</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <!-- Conditional Fields: Student -->
        <div v-if="form.role === 'student'">
            <h3>Student Details</h3>
            <div class="form-row">
                <label for="fullName">Full Name:</label>
                <input v-model="form.fullName" type="text" id="fullName" required />
            </div>

            <div class="form-row">
                <label for="matricNo">Matric No:</label>
                <input v-model="form.matricNo" type="text" id="matricNo" required />
            </div>

            <div class="form-row">
                <label for="email">Email:</label>
                <input v-model="form.email" type="email" id="email" required />
            </div>

            <div class="form-row">
                <label for="yearOfStudy">Year of Study:</label>
                <input v-model="form.yearOfStudy" type="number" id="yearOfStudy" required min="1" max="6"/> <!-- Changed to type number, added min/max -->
            </div>

            <div class="form-row">
                <label for="programme">Programme:</label>
                <input v-model="form.programme" type="text" id="programme" required />
            </div>
        </div>

        <!-- Conditional Fields: Lecturer -->
        <div v-if="form.role === 'lecturer'">
            <h3>Lecturer Details</h3>
            <div class="form-row">
                <label for="lecturerName">Full Name:</label>
                <input v-model="form.lecturerName" type="text" id="lecturerName" required />
            </div>

            <div class="form-row">
                <label for="lecturerStaffId">Staff ID:</label>
                <input v-model="form.lecturerStaffId" type="text" id="lecturerStaffId" required />
            </div>

            <div class="form-row">
                <label for="lecturerEmail">Email:</label>
                <input v-model="form.lecturerEmail" type="email" id="lecturerEmail" required />
            </div>

            <div class="form-row">
                <label for="lecturerDepartment">Department:</label>
                <input v-model="form.lecturerDepartment" type="text" id="lecturerDepartment" required />
            </div>
        </div>

        <!-- Conditional Fields: Advisor -->
        <div v-if="form.role === 'advisor'">
            <h3>Academic Advisor Details</h3>
            <div class="form-row">
                <label for="advisorName">Full Name:</label>
                <input v-model="form.advisorName" type="text" id="advisorName" required />
            </div>

            <div class="form-row">
                <label for="advisorStaffId">Staff ID:</label>
                <input v-model="form.advisorStaffId" type="text" id="advisorStaffId" required />
            </div>

            <div class="form-row">
                <label for="advisorEmail">Email:</label>
                <input v-model="form.advisorEmail" type="email" id="advisorEmail" required />
            </div>

            <div class="form-row">
                <label for="advisorDepartment">Department:</label>
                <input v-model="form.advisorDepartment" type="text" id="advisorDepartment" required />
            </div>

            <div class="form-row">
                <label for="adviseeQuota">Advisee Quota:</label>
                <input v-model="form.adviseeQuota" type="number" id="adviseeQuota" required />
            </div>
        </div>

        <button type="submit">Register</button>

        <p class="success" v-if="successMessage">{{ successMessage }}</p>
        <p class="error" v-if="errorMessage">{{ errorMessage }}</p>

        <p class="login-link">
          Already have an account?
          <router-link to="/">Login here</router-link> <!-- Corrected to '/' for login route -->
        </p>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MyRegister',
  data() {
    return {
      successMessage: '',
      errorMessage: '',
      form: {
        username: '',
        password: '',
        confirmPassword: '',
        role: null, // <--- Set default role to null (unselected)
        // Student fields (initialize to empty strings)
        fullName: '',
        matricNo: '',
        email: '',
        yearOfStudy: '',
        programme: '',
        // Lecturer fields
        lecturerName: '',
        lecturerStaffId: '',
        lecturerEmail: '',
        lecturerDepartment: '',
        // Advisor fields
        advisorName: '',
        advisorStaffId: '',
        advisorEmail: '',
        advisorDepartment: '',
        adviseeQuota:''
      }
    };
  },
  watch: {
    // Watch for changes in role and clear conditional fields if role changes
    'form.role'(newRole, oldRole) {
      if (newRole !== 'student' && oldRole === 'student') {
        this.form.fullName = '';
        this.form.matricNo = '';
        this.form.email = '';
        this.form.yearOfStudy = '';
        this.form.programme = '';
      }
      if (newRole !== 'lecturer' && oldRole === 'lecturer') {
        this.form.lecturerName = '';
        this.form.lecturerStaffId = '';
        this.form.lecturerEmail = '';
        this.form.lecturerDepartment = '';
      }
      if (newRole !== 'advisor' && oldRole === 'advisor') {
        this.form.advisorName = '';
        this.form.advisorStaffId = '';
        this.form.advisorEmail = '';
        this.form.advisorDepartment = '';
        this.form.adviseeQuota = '';
      }
    }
  },
  methods: {
    async register() {
      this.errorMessage = '';
      this.successMessage = '';

      // Basic client-side validation
      if (this.form.password !== this.form.confirmPassword) {
        this.errorMessage = "Passwords do not match.";
        return;
      }
      if (this.form.role === null) { // Check if a role has been selected
        this.errorMessage = "Please select a role.";
        return;
      }

      const body = {
        username: this.form.username,
        password: this.form.password,
        role: this.form.role
      };

      // Add role-specific fields and perform client-side validation
      if (this.form.role === 'student') {
        if (!this.form.fullName || !this.form.matricNo || !this.form.email || !this.form.yearOfStudy || !this.form.programme) {
            this.errorMessage = "All student details (Full Name, Matric No, Email, Year of Study, Programme) are required.";
            return;
        }
        body.full_name = this.form.fullName;
        body.matric_no = this.form.matricNo;
        body.email = this.form.email;
        body.year_of_study = parseInt(this.form.yearOfStudy); // Ensure it's an integer
        body.programme = this.form.programme;
      } else if (this.form.role === 'lecturer') {
        if (!this.form.lecturerName || !this.form.lecturerStaffId || !this.form.lecturerEmail || !this.form.lecturerDepartment) {
            this.errorMessage = "All lecturer details (Full Name, Staff ID, Email, Department) are required.";
            return;
        }
        body.full_name = this.form.lecturerName;
        body.email = this.form.lecturerEmail;
        body.lecturer_id = this.form.lecturerStaffId; // Assuming staff_id maps to lecturer_id in DB
        body.department = this.form.lecturerDepartment;
      } else if (this.form.role === 'advisor') {
        if (!this.form.advisorName || !this.form.advisorStaffId || !this.form.advisorEmail || !this.form.advisorDepartment || !this.form.adviseeQuota) {
            this.errorMessage = "All advisor details (Full Name, Staff ID, Email, Department, Advisee Quota) are required.";
            return;
        }
        body.full_name = this.form.advisorName;
        body.email = this.form.advisorEmail;
        body.advisor_id = this.form.advisorStaffId; // Assuming staff_id maps to advisor_id in DB
        body.department = this.form.advisorDepartment;
        body.advisee_quota = parseInt(this.form.adviseeQuota); // Ensure it's an integer
      }

      try {
        const res = await fetch('http://localhost:8000/api/register', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });

        const data = await res.json();

        if (res.ok) {
          this.successMessage = 'Registration successful! Redirecting to login...';
          this.errorMessage = '';
          // Reset form fields after successful registration
          this.resetForm(); // <--- Call resetForm after successful registration

          // Redirect to login page after a short delay
          setTimeout(() => {
            this.$router.push('/'); // <--- Redirect to the login route (root path)
          }, 2000); // Redirect after 2 seconds

        } else {
          this.errorMessage = data.error || 'Registration failed.';
          console.error('Registration Error:', data);
        }
      } catch (err) {
        console.error('Network error during registration:', err);
        this.errorMessage = 'Network error. Could not connect to the server. Please try again.';
      }
    },
    resetForm() {
        this.form = {
            username: '',
            password: '',
            confirmPassword: '',
            role: null, // Reset to null
            fullName: '',
            matricNo: '',
            email: '',
            yearOfStudy: '',
            programme: '',
            lecturerName: '',
            lecturerStaffId: '',
            lecturerEmail: '',
            lecturerDepartment: '',
            advisorName: '',
            advisorStaffId: '',
            advisorEmail: '',
            advisorDepartment: '',
            adviseeQuota:''
        };
    }
  }
};
</script>

<style scoped>
.register-container {
  max-width: 500px; /* Adjusted max-width to better fit all fields */
  margin: 20px auto; /* Added margin-top/bottom */
  padding: 30px; /* Increased padding */
  border: 1px solid #ddd;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); /* Slightly more prominent shadow */
  background-color: #fff;
}

h2 {
  text-align: center;
  color: #333;
  margin-bottom: 25px; /* Increased margin */
  font-size: 24px; /* Larger heading */
}

h3 { /* Style for conditional section titles */
    text-align: center;
    color: #444;
    margin-top: 25px;
    margin-bottom: 15px;
    font-size: 20px;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}

.form-row {
  display: flex;
  align-items: center;
  margin-bottom: 15px;
}

.form-row label {
  width: 150px;
  margin-right: 15px; /* Increased margin */
  font-weight: bold;
  text-align: right;
  color: #555;
}

.form-row input,
.form-row select {
  flex: 1;
  padding: 10px 12px; /* Increased padding */
  border: 1px solid #ccc;
  border-radius: 5px; /* Slightly more rounded corners */
  box-sizing: border-box;
  font-size: 16px; /* Larger font size for inputs */
}

button {
  width: 100%;
  padding: 12px; /* Increased padding */
  background-color: #28a745; /* Green for register button */
  color: white;
  border: none;
  border-radius: 5px;
  font-size: 18px; /* Larger font size */
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.2s ease;
  margin-top: 20px; /* Added margin to separate from fields */
}

button:hover {
  background-color: #218838;
  transform: translateY(-2px); /* Slight lift effect */
}

.error {
  color: #dc3545; /* Bootstrap red */
  margin-top: 15px;
  text-align: center;
  font-weight: bold;
}

.success {
  color: #28a745; /* Bootstrap green */
  margin-top: 15px;
  text-align: center;
  font-weight: bold;
}

.login-link {
  margin-top: 25px; /* Increased margin */
  text-align: center;
  font-size: 15px;
  color: #666;
}

.login-link a {
  color: #007bff;
  text-decoration: none;
  font-weight: bold;
}

.login-link a:hover {
  text-decoration: underline;
}
</style>