<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;
use OpenAI;
use Symfony\Component\Routing\Annotation\Route;


class ResumeController extends AbstractController
{
    public function upload(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $file = $request->files->get('resume');

            if ($file && in_array($file->getClientOriginalExtension(), ['pdf','docx'])) {
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $filename;
                $file->move(dirname($path), basename($path));

                $resumeText = '';
                if ($file->getClientOriginalExtension() === 'pdf') {
                    $parser = new PdfParser();
                    $pdf = $parser->parseFile($path);
                    $resumeText = $pdf->getText();
                } elseif ($file->getClientOriginalExtension() === 'docx') {
                    $phpWord = IOFactory::load($path);
                    foreach ($phpWord->getSections() as $section) {
                        foreach ($section->getElements() as $element) {
                            if (method_exists($element, 'getText')) {
                                $resumeText .= $element->getText() . " ";
                            }
                        }
                    }
                }

                $request->getSession()->set('resumeText', $resumeText);
                return $this->redirectToRoute('app_resume_result');
            }
        }

        return $this->render('resume/upload.html.twig');
    }

    #[Route('/result', name: 'app_resume_result')]
    public function result(Request $request): Response
    {
        $resumeText = $request->getSession()->get('resumeText', 'No resume uploaded.');

        // $client = OpenAI::client($_ENV['OPENAI_API_KEY']);

         $client = OpenAI::factory()
        ->withApiKey($_ENV['OPENAI_API_KEY'])
        ->withBaseUri('https://openrouter.ai/api/v1')
        ->make();


        echo'<pre>';
        $response = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an AI resume analyzer.'],
                ['role' => 'user', 'content' => "Here is a resume:\n$resumeText\n\nEvaluate strengths, weaknesses, and suggestions."]
                ]
            ]);
            
            $analysis = $response['choices'][0]['message']['content'] ?? 'No response from AI.';
            // print_r($response); // Debugging line to check client initialization 
            
            // exit ;
        return $this->render('resume/result.html.twig', ['analysis' => $analysis]);
    }
}
