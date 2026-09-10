@extends('layouts.app')

@section('content')
<div class=" login-container  ">
        <div class="row w-100    ">
            
            <div class="col-md-6  p-0  ">
                <a href="./login.html">
                    <img src="{{ asset('image/lev.png') }}" alt="Loginho" class="login-logo">
                </a>
               <div class=" login-form-side   ">
                
                <div class="container  ">
                    
                    <h3 class="mb-2">Login</h3>
                    <p class="text-muted mb-3" style="font-size: 14px;">Faça seu login para continuar </p>
                    
                    <form>
                        <div class="">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" placeholder="seunome@gmail.com" required>
                        </div>
                        <div class="">
                            <label for="password">Senha</label>
                            <input type="password" class="form-control" placeholder="Password" required>
                        </div>
                        
                        <div class="mb-2 d-flex justify-content-between align-items-center" style="font-size: 13px;">
                            <div>
                                <input type="checkbox" id="rememberMe" class="form-check-input me-1">
                                <label for="rememberMe">Lembrar-me</label>
                            </div>
                            <a href="./reset-password.html" class="text-decoration-none" style="color: #ff6b35;">Esqueceu a senha ?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-login w-100 mb-2" style="font-size: 16px; font-weight: bold;">Login</button>
                        
                        <div class="text-center mt-2" style="font-size: 13px;">
                            Nao tem uma conta? <a href="register.html" style="color: #ff6b35;">Registar-se</a>
                        </div>
                    </form>
                
                </div>
               </div>
                
            </div>
            
               <div class="col-md-6 p-0 d-none d-md-block">
                <div id="loginCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#loginCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                        <button type="button" data-bs-target="#loginCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                        <button type="button" data-bs-target="#loginCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                    </div>
                    <div class="carousel-inner">
                        <div class="carousel-item active" style="background-image: url('/image/mesa.png')">
                            <div class="carousel-caption">
                                <h4 class="text-white">Desperte o Poder da Nossa Solução Intuitiva de Ponto de Venda</h4>
                                <p style="font-size: 14px;">Experimente o futuro do varejo com a nossa plataforma de PDV fácil de usar.</p>
                            </div>
                        </div>
                        <div class="carousel-item" style="background-image: url('/image/mo.png')">
                            <div class="carousel-caption">
                                <h4 class="text-white">Transforme Seu Negócio com Tecnologia de Ponta</h4>
                                <p style="font-size: 14px;">Gestão simplificada e eficiente para seu ponto de venda.</p>
                            </div>
                        </div>
                        <div class="carousel-item" style="background-image: url('/image/mo1.png')">
                            <div class="carousel-caption">
                                <h4 class="text-white">Controle Total em Suas Mãos</h4>
                                <p style="font-size: 14px;">Relatórios instantâneos, integração perfeita, produtividade máxima.</p>
                            </div>
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#loginCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#loginCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Próximo</span>
                    </button>
                </div>
            </div>
        </div>
        
    </div>
@endsection
