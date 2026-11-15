import {Component,inject,OnInit} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FormsModule} from '@angular/forms';
import {HttpClient} from '@angular/common/http';
import {RouterLink} from '@angular/router';

@Component({
 standalone:true,
 imports:[CommonModule,FormsModule,RouterLink],
 template:`
 <section class="hero">
  <small>O SEU CASAMENTO COMEÇA AQUI</small>
  <h1>Encontre os fornecedores certos para um dia inesquecível.</h1>
  <p>Descubra profissionais de casamentos em Moçambique. Compare opções e peça ajuda à LIRANDZU para chegar ao fornecedor ideal.</p>
  <div class="search">
   <input [(ngModel)]="q" (keyup.enter)="load()" placeholder="Fotografia, decoração, catering..." aria-label="Pesquisar fornecedores">
   <select [(ngModel)]="category" (change)="load()" aria-label="Categoria"><option value="">Todas as categorias</option><option *ngFor="let c of categories" [value]="c.slug">{{c.name}}</option></select>
   <input [(ngModel)]="city" (keyup.enter)="load()" placeholder="Cidade" aria-label="Cidade">
   <button type="button" (click)="load()">Pesquisar</button>
  </div>
 </section>
 <section class="content">
  <div class="request-panel tracking-panel">
   <small>ACOMPANHAMENTO LIRANDZU</small><h2>Acompanhe o seu pedido</h2>
   <form (ngSubmit)="trackRequest()"><label>Referência do pedido<input name="trackingReference" [(ngModel)]="trackingReference" placeholder="LIR-XXXXXXXX" required></label><button>Consultar</button></form>
   <p class="feedback" role="status">{{trackingError}}</p>
   <div *ngIf="trackingResult" class="reference">
    <b>{{trackingResult.reference_code}}</b> · {{trackingResult.business_name}} · Estado: {{statusLabel(trackingResult.status)}}
    <p>{{trackingResult.event_city}} · {{trackingResult.event_date||'Data por definir'}}</p>
    <div *ngFor="let msg of trackingResult.messages"><p>{{msg.message}} <small>{{msg.created_at}}</small></p></div>
    <form (ngSubmit)="sendTrackingMessage()"><label>Enviar mensagem à equipa LIRANDZU<textarea name="trackingMessage" [(ngModel)]="trackingMessage" required minlength="2"></textarea></label><button [disabled]="trackingSending">{{trackingSending?'A enviar...':'Enviar mensagem'}}</button></form>
   </div>
  </div>
  <div class="section-head"><div><small>ESCOLHA COM CONFIANÇA</small><h2>Fornecedores para o seu grande dia</h2></div><span>{{visibleProviders.length}} resultados</span></div>
  <div class="catalog-toolbar">
   <button type="button" [class.secondary]="!showFavoritesOnly" (click)="showFavoritesOnly=false">Todos os fornecedores</button>
   <button type="button" [class.secondary]="showFavoritesOnly" (click)="showFavoritesOnly=true">♥ Favoritos ({{favoriteIds.length}})</button>
   <span>Compare até 3 fornecedores antes de decidir.</span>
  </div>
  <div class="cards">
   <article *ngFor="let p of visibleProviders">
    <div class="cover" [style.backgroundImage]="p.cover_image ? 'url('+p.cover_image+')' : ''"><span *ngIf="p.featured">EM DESTAQUE</span></div>
    <small>{{p.category}} · {{p.city}}</small><h3>{{p.business_name}} <span *ngIf="p.verified" class="verified">✓</span></h3>
    <p>{{p.description}}</p><b>{{p.indicative_price ? (p.indicative_price|number:'1.0-0')+' MZN' : 'Preço sob consulta'}}</b>
    <a class="profile-link" [routerLink]="['/fornecedor',p.slug]">Ver perfil e serviços</a>
    <div class="card-actions">
     <button type="button" class="secondary" (click)="toggleFavorite(p)">{{isFavorite(p)?'♥ Guardado':'♡ Favorito'}}</button>
     <button type="button" class="secondary" (click)="toggleCompare(p)">{{isCompared(p)?'Remover comparação':'＋ Comparar'}}</button>
    </div>
    <button type="button" (click)="selected=p;resetRequest()">Solicitar contacto</button>
   </article>
  </div>
  <p *ngIf="!visibleProviders.length" class="empty">{{showFavoritesOnly?'Ainda não guardou fornecedores favoritos.':'Não encontrámos fornecedores com estes critérios. Experimente alterar a pesquisa ou volte mais tarde.'}}</p>
  <section *ngIf="compareItems.length" class="request-panel comparison-panel">
   <div class="section-head"><div><small>DECIDA COM INFORMAÇÃO</small><h2>Comparar fornecedores</h2></div><button class="secondary" type="button" (click)="compareItems=[]">Limpar comparação</button></div>
   <p class="feedback">{{compareFeedback}}</p>
   <div class="comparison-grid">
    <article *ngFor="let p of compareItems"><h3>{{p.business_name}}</h3><p>{{p.category}} · {{p.city}}</p><p>{{p.description}}</p><b>{{p.indicative_price?(p.indicative_price|number:'1.0-0')+' MZN':'Preço sob consulta'}}</b><a class="profile-link" [routerLink]="['/fornecedor',p.slug]">Ver perfil</a></article>
   </div>
  </section>
  <section class="request-panel" *ngIf="selected">
   <button class="close" type="button" (click)="selected=null" aria-label="Fechar">×</button>
   <small>PEDIDO DE CONTACTO · {{selected.business_name}}</small><h2>Vamos aproximá-lo deste fornecedor.</h2>
   <p>A sua solicitação será recebida pela equipa LIRANDZU para encaminhamento.</p>
   <form (ngSubmit)="sendRequest()">
    <label>Nome completo<input name="customer_name" [(ngModel)]="request.customer_name" required minlength="3"></label>
    <label>Telefone<input name="customer_phone" [(ngModel)]="request.customer_phone" required minlength="8"></label>
    <label>E-mail (opcional)<input type="email" name="customer_email" [(ngModel)]="request.customer_email"></label>
    <label>Cidade do evento<input name="event_city" [(ngModel)]="request.event_city" required></label>
    <label>Data do evento<input type="date" name="event_date" [(ngModel)]="request.event_date"></label>
    <label>Orçamento indicativo em MZN<input type="number" min="0" name="budget" [(ngModel)]="request.budget"></label>
    <label>Conte-nos o que procura<textarea name="message" [(ngModel)]="request.message" required minlength="10"></textarea></label>
    <button [disabled]="sending">{{sending?'A enviar...':'Enviar pedido'}}</button>
   </form>
   <p class="feedback" role="status">{{feedback}}</p><div *ngIf="reference" class="reference">Referência do pedido: <b>{{reference}}</b></div>
  </section>
 </section>
 `
})
export class HomeComponent implements OnInit {
 private http=inject(HttpClient);
 providers:any[]=[];categories:any[]=[];q='';city='';category='';selected:any=null;feedback='';reference='';sending=false;
 trackingReference='';trackingError='';trackingResult:any=null;trackingMessage='';trackingSending=false;
 favoriteIds:number[]=this.readFavoriteIds();showFavoritesOnly=false;compareItems:any[]=[];compareFeedback='';
 request:any={customer_name:'',customer_phone:'',customer_email:'',event_city:'',event_date:'',budget:null,message:''};
 get visibleProviders(){return this.showFavoritesOnly?this.providers.filter(p=>this.favoriteIds.includes(Number(p.id))):this.providers;}
 ngOnInit(){this.http.get<any>('/api/categories').subscribe({next:r=>this.categories=r.data||[]});this.load();}
 load(){const params:any={};if(this.q.trim())params.q=this.q.trim();if(this.city.trim())params.city=this.city.trim();if(this.category)params.category=this.category;this.http.get<any>('/api/providers',{params}).subscribe({next:r=>this.providers=r.data||[],error:()=>this.providers=[]});}
 private readFavoriteIds(){try{return typeof localStorage==='undefined'?[]:JSON.parse(localStorage.getItem('lirandzu_favorites')||'[]').map((id:any)=>Number(id)).filter((id:number)=>Number.isFinite(id));}catch{return [];}}
 private saveFavoriteIds(){if(typeof localStorage!=='undefined')localStorage.setItem('lirandzu_favorites',JSON.stringify(this.favoriteIds));}
 isFavorite(p:any){return this.favoriteIds.includes(Number(p.id));}
 toggleFavorite(p:any){const id=Number(p.id);this.favoriteIds=this.isFavorite(p)?this.favoriteIds.filter(x=>x!==id):[...this.favoriteIds,id];this.saveFavoriteIds();}
 isCompared(p:any){return this.compareItems.some(x=>Number(x.id)===Number(p.id));}
 toggleCompare(p:any){if(this.isCompared(p)){this.compareItems=this.compareItems.filter(x=>Number(x.id)!==Number(p.id));this.compareFeedback='Fornecedor removido da comparação.';return;}if(this.compareItems.length>=3){this.compareFeedback='Pode comparar no máximo três fornecedores de cada vez.';return;}this.compareItems=[...this.compareItems,p];this.compareFeedback='Fornecedor adicionado à comparação.';}
 resetRequest(){this.feedback='';this.reference='';this.request={customer_name:'',customer_phone:'',customer_email:'',event_city:'',event_date:'',budget:null,message:''};}
 trackRequest(){this.trackingError='';this.trackingResult=null;const ref=this.trackingReference.trim().toUpperCase();this.http.get<any>('/api/service-requests/'+encodeURIComponent(ref)+'/status').subscribe({next:r=>this.trackingResult=r.data,error:e=>this.trackingError=e.error?.error||'Não foi possível consultar a referência.'});}
 sendTrackingMessage(){if(this.trackingSending)return;this.trackingSending=true;this.http.post<any>('/api/service-requests/'+encodeURIComponent(this.trackingReference.trim().toUpperCase())+'/messages',{message:this.trackingMessage}).subscribe({next:r=>{this.trackingError=r.message;this.trackingMessage='';this.trackingSending=false;this.trackRequest();},error:e=>{this.trackingError=e.error?.error||'Não foi possível enviar a mensagem.';this.trackingSending=false;}});}
 sendRequest(){if(this.sending||!this.selected)return;this.sending=true;this.http.post<any>('/api/service-requests',{...this.request,provider_id:this.selected.id}).subscribe({next:r=>{this.feedback=r.message;this.reference=r.reference_code;this.sending=false;},error:e=>{this.feedback=e.error?.error||'Não foi possível enviar o pedido.';this.sending=false;}});}
 statusLabel(status:string){return ({submitted:'Recebido',forwarded:'Encaminhado pela LIRANDZU',answered:'Resposta recebida',closed:'Concluído',cancelled:'Cancelado'} as any)[status]||status;}
}
