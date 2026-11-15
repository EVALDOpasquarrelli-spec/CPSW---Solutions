import {Component,inject,OnInit} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FormsModule} from '@angular/forms';
import {HttpClient} from '@angular/common/http';

@Component({
 standalone:true,imports:[CommonModule,FormsModule],
 template:`
 <section class="form application-form">
  <small>PARCERIA LIRANDZU</small><h1>Apresente o seu negócio.</h1>
  <p>A candidatura será analisada pela equipa LC CONSULTORIA antes da publicação no catálogo.</p>
  <form (ngSubmit)="submit()">
   <label>Nome do responsável<input name="applicant_name" [(ngModel)]="data.applicant_name" required minlength="3"></label>
   <label>Nome comercial<input name="business_name" [(ngModel)]="data.business_name" required minlength="2"></label>
   <label>E-mail<input type="email" name="email" [(ngModel)]="data.email" required></label>
   <label>Telefone<input name="phone" [(ngModel)]="data.phone" required minlength="8"></label>
   <label>Categoria principal<select name="category_id" [(ngModel)]="data.category_id" required><option [ngValue]="null" disabled>Seleccione uma categoria</option><option *ngFor="let category of categories" [ngValue]="category.id">{{category.name}}</option></select></label>
   <label>Cidade<input name="city" [(ngModel)]="data.city" required minlength="2"></label>
   <label>Descrição dos serviços<textarea name="description" [(ngModel)]="data.description" required minlength="20" maxlength="3000"></textarea></label>
   <button [disabled]="sending||!categories.length">{{sending?'A enviar...':'Enviar candidatura'}}</button>
  </form>
  <p class="feedback" role="status">{{feedback}}</p>
 </section>`
})
export class ApplyComponent implements OnInit {
 private http=inject(HttpClient);categories:any[]=[];feedback='';sending=false;
 data:any={applicant_name:'',business_name:'',email:'',phone:'',category_id:null,city:'',description:''};
 ngOnInit(){this.http.get<any>('/api/categories').subscribe({next:r=>{this.categories=r.data||[];this.data.category_id=this.categories[0]?.id??null;},error:()=>this.feedback='Não foi possível carregar as categorias. Actualize a página.'});}
 submit(){if(this.sending)return;this.sending=true;this.http.post<any>('/api/provider-applications',this.data).subscribe({next:r=>{this.feedback=r.message;this.sending=false;this.data={applicant_name:'',business_name:'',email:'',phone:'',category_id:this.categories[0]?.id??null,city:'',description:''};},error:e=>{this.feedback=e.error?.error||'Não foi possível enviar a candidatura.';this.sending=false;}});}
}
