<?php

namespace App\Http\Controllers;

use Auth;
use App\Project;
use App\Coordenador;
use App\Membro;
use App\Pessoa;
use DB;
use Illuminate\Http\Request;
use Intervention\Image\ImageManagerStatic as Image;
use Crypt;

class ProjectController extends Controller
{
    public function addProject(Request $request)
    {
        // dd($request);

        $this->validate($request, [
            'titulo'    => 'required|min:3|max:1000',
            'tipo'      => 'required|string|min:3|max:255',
            'descricao' => 'required|min:3|max:10000',
            'status'    => 'required',
          // 'dataTermino'           => 'required',
          // 'imagemCapa'            => 'required',
        ]);

        $imagemCapa = "";
        if ($request->hasFile('imagemCapa') && $request->file('imagemCapa')->isValid()) {
            $ext = strtolower(request()->imagemCapa->getClientOriginalExtension());

            if (!in_array($ext, array("jpg", "png", "jpeg", "gif", "bmp"))) {
                $validator->errors()->add("imagemcapa", "Formato de imagem inválido, utilize imagem jpg ou png");
            } else {
                $imagemCapa = 'c' . time() . '.' . $ext;
                // $upload = $request->imagemCapa->storeAs('projects', 'abc.png');
                $thumbPath = storage_path('app/public/imagens/projects/' . $imagemCapa);
                Image::configure(array('driver' => 'imagick'));
                $image = Image::make(request()->imagemCapa->path());
                $image->fit(300, 300)->save($thumbPath);
            }
            $idProjeto = Project::create([
                'titulo'       => $request->titulo,
                'conteudo'     => $request->descricao,
                'tipo'         => $request->tipo,
                'status'       => $request->status,
                'dataTermino'  => $request->dataTermino,
                'visualizacao' => '0',
                'user_id'      => Auth::user()->id,
                'imagemCapa'   => $imagemCapa,
            ]);

            return redirect()->route('select_project', ['idProject' => Crypt::encrypt($request->idProject)])->with('sucesso', 'Projeto cadastrado com sucesso!');
        } else {
            $idProjeto = Project::create([
                'titulo'       => $request->titulo,
                'conteudo'     => $request->descricao,
                'tipo'         => $request->tipo,
                'status'       => $request->status,
                'dataTermino'  => $request->dataTermino,
                'visualizacao' => '0',
                'user_id'      => Auth::user()->id,
                'imagemCapa'   => "imagemDefault.png",
            ]);
            return redirect()->route('select_project', ['idProject' => Crypt::encrypt($request->idProject)])->with('sucesso', 'Projeto cadastrado com sucesso!');
        }
    }

    public function editProject(Request $request)
    {
        if (!is_null(Auth::user())) { //logado
            // dd($request);
            $resultado = Project::where('id', '=', Crypt::decrypt($request->idProject))->first();
            return view('config/config_project_edit', ['projeto' => $resultado]);
        } else {
            return redirect('home');
        }
    }

    public function updateProject(Request $request)
    {
        // dd($request);
        $this->validate($request, [
            'titulo'    => 'required|min:3|max:1000',
            'tipo'      => 'required|string|min:3|max:255',
            'descricao' => 'required|min:3|max:10000',
            'status'    => 'required',
        // 'dataTermino'           => 'required',
        ]);
        $imagemCapa = "";
        if ($request->hasFile('imagemCapa') && $request->file('imagemCapa')->isValid()) {
            $ext = strtolower(request()->imagemCapa->getClientOriginalExtension());

            if (!in_array($ext, array("jpg", "png", "jpeg", "gif", "bmp"))) {
                $validator->errors()->add("imagemcapa", "Formato de imagem inválido, utilize imagem jpg ou png");
            } else {
                $imagemCapa = 'c' . time() . '.' . $ext;
                $thumbPath  = storage_path('app/public/imagens/projects/' . $imagemCapa);
                Image::configure(array('driver' => 'imagick'));
                $image = Image::make(request()->imagemCapa->path());
                $image->fit(300, 300)->save($thumbPath);
            }
            //deleta imagem
            $resultadoIMG = Project::where('id', '=', $request->id)->first()->imagemCapa;
            if ($resultadoIMG != "imagemDefault.png") {
                unlink('storage/imagens/projects/' . $resultadoIMG);
            }
            //atualiza
            $resultado = Project::where('id', '=', $request->id)
            ->update(['titulo' => $request->titulo, 'conteudo' => $request->descricao, 'status' => $request->status, 'dataTermino' => $request->dataTermino, 'tipo' => $request->tipo, 'imagemCapa' => $imagemCapa,]);
        } else {
            //atualiza
            $resultado = Project::where('id', '=', $request->id)
            ->update(['titulo' => $request->titulo, 'conteudo' => $request->descricao, 'status' => $request->status, 'dataTermino' => $request->dataTermino, 'tipo' => $request->tipo,]);
        }

        return redirect()->route('config_project')->with('sucesso', 'Projeto atualizado com sucesso!');
    }

    public function deleteProject(Request $request)
    {
        $nomeimagem = Project::where('id', '=', $request->idProjeto)->first()->imagemCapa;
        Coordenador::where('project_id', '=', $request->idProjeto)->delete();
        Membro::where('project_id', '=', $request->idProjeto)->delete();
        // dd($nomeimagem);
        if ($nomeimagem == '') {
            $resultado = Project::where('id', 'ilike', $request->idProjeto)->delete();
        } else {
            if ($nomeimagem != "imagemDefault.png") {
                unlink('storage/imagens/projects/' . $nomeimagem);
            }
            $resultado = Project::where('id', 'ilike', $request->idProjeto)->delete();
        }
        return redirect()->route('config_project');
    }

    public function allProject()
    {
        $resultado = Project::orderBy('created_at', 'desc')->paginate(10);
        return view('project', ['allProject' => $resultado]);
    }

    public function showProject(Request $request)
    {

        //atualizar o numero de views
        if (Auth::user() == null) {
            DB::table('projects')->where('id', '=', $request->idProject)->increment('visualizacao', 1);
        }
        //mostrar projeto
        $resultado            = Project::where('id', '=', $request->idProject)->first();
        $resultadoCoordenador = Coordenador::where('project_id', '=', $request->idProject)->get();
        $resultadoMembros     = Membro::where('project_id', '=', $request->idProject)->get();
        return view('projectDetail', ['projectDetail' => $resultado,'allCoordenadoresProject' => $resultadoCoordenador,'allMembrosProject' => $resultadoMembros,]);
    }

    public function createProjects()
    {
        return view('config/config_project_create');
    }

    public function selectProjects(Request $request)
    {
        if (!is_null(Auth::user())) { //logado
            // dd($request);
            $resultadoAllCoordenadores = Pessoa::where('tipo', '=', 'Docente')->get();
            $resultadoAllMembros       = Pessoa::get();
            $resultadoCoordenador      = Coordenador::where('project_id', '=', Crypt::decrypt($request->idProject))->get();
            $resultadoMembro           = Membro::where('project_id', '=', Crypt::decrypt($request->idProject))->get();
            return view('config/config_project_select', ['idProjeto' => Crypt::decrypt($request->idProject), 'resultadoAllCoordenadores' => $resultadoAllCoordenadores,'resultadoAllMembros' => $resultadoAllMembros,'allCoordenadores' => $resultadoCoordenador, 'allMembros' => $resultadoMembro]);
        } else { //nao logado
            return redirect('home');
        }
    }

    public function portfolio()
    {
        $resultado = Project::where('status', 'ilike', 'Finalizado')->orderBy('created_at', 'desc')->paginate(10);
        return view('portfolio', ['allProject' => $resultado,]);
    }
}
