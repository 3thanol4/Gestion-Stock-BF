import { Component, OnInit, inject, signal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule],
  templateUrl: './login.html'
})
export class LoginComponent implements OnInit {
  private authService = inject(AuthService);
  private fb = inject(FormBuilder);
  private router = inject(Router);

  loginForm!: FormGroup;
  errorMessage = signal<string | null>(null);
  isLoading = signal<boolean>(false);

  ngOnInit() {
    // Si déjà connecté, rediriger vers dashboard
    if (this.authService.getToken()) {
       this.router.navigate(['/dashboard']);
    }

    this.loginForm = this.fb.group({
      username: ['', Validators.required],
      password: ['', Validators.required]
    });
  }

  onSubmit() {
    if (this.loginForm.valid) {
      this.isLoading.set(true);
      this.errorMessage.set(null);
      
      const username = (this.loginForm.value.username ?? '').trim();
      const password = this.loginForm.value.password ?? '';

      this.authService.login(username, password).subscribe({
        next: (res) => {
          this.isLoading.set(false);
          this.router.navigate(['/dashboard']);
        },
        error: (err) => {
          this.isLoading.set(false);
          this.errorMessage.set(err.error?.message || 'Nom d\'utilisateur ou mot de passe incorrect.');
        }
      });
    }
  }
}
