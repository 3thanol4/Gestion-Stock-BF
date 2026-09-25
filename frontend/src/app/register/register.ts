import { Component, OnInit, inject, signal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-register',
  imports: [ReactiveFormsModule],
  templateUrl: './register.html'
})
export class RegisterComponent implements OnInit {
  private authService = inject(AuthService);
  private fb = inject(FormBuilder);
  private router = inject(Router);

  registerForm!: FormGroup;
  errorMessage = signal<string | null>(null);
  successMessage = signal<string | null>(null);
  isLoading = signal<boolean>(false);
  profileLoaded = signal<boolean>(false);

  // Signals pour la réactivité immédiate dans le template
  isPlatformAdmin = signal<boolean>(false);
  isEntrepriseAdmin = signal<boolean>(false);

  ngOnInit() {
    if (!this.authService.getToken()) {
      this.router.navigate(['/login']);
      return;
    }

    this.registerForm = this.fb.group({
      name: ['', Validators.required],
      username: ['', Validators.required],
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(8)]],
      role: ['gestionnaire_stock', Validators.required],
      entreprise_nom: ['']
    });

    // Charger le profil pour déterminer les droits
    this.authService.fetchProfile().subscribe(user => {
      if (user && user.roles) {
        const isPlatform = user.roles.some((r: any) => r.name === 'administrateur_plateforme');
        const isEntreprise = user.roles.some((r: any) => r.name === 'administrateur_entreprise');

        this.isPlatformAdmin.set(isPlatform);
        this.isEntrepriseAdmin.set(isEntreprise);
        this.profileLoaded.set(true);
      }
    });
  }

  onSubmit() {
    if (this.registerForm.valid) {
      this.isLoading.set(true);
      this.errorMessage.set(null);
      this.successMessage.set(null);

      this.authService.register(this.registerForm.value).subscribe({
        next: (res) => {
          this.isLoading.set(false);
          this.successMessage.set('Collaborateur créé avec succès.');
          this.registerForm.reset();
          this.registerForm.patchValue({ role: 'gestionnaire_stock' });
        },
        error: (err) => {
          this.isLoading.set(false);
          if (err.error?.errors) {
            const firstError = Object.values(err.error.errors)[0] as string[];
            this.errorMessage.set(firstError[0]);
          } else if (err.error?.message) {
            this.errorMessage.set(err.error.message);
          } else {
            this.errorMessage.set('Erreur lors de la création du collaborateur.');
          }
        }
      });
    }
  }

  goBack() {
    this.router.navigate(['/dashboard']);
  }
}
