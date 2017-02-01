<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use JonnyW\PhantomJs\Client;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DefaultController extends Controller
{
    /**
     * @Route("/formula", name="formula")
     */
    public function imageAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', array(
            'base_dir' => realpath($this->container->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
            'math' => $request->get('math')
        ));
    }

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $client = \JonnyW\PhantomJs\Client::getInstance();
        $client->getEngine()->setPath(
            \PhantomInstaller\Installer::getOS() == 'windows'
                ? __DIR__ . "/../../../bin/phantomjs.exe"
                : __DIR__ . "/../../../bin/phantomjs"
        );

        $procedureLoader =
            \JonnyW\PhantomJs\DependencyInjection\ServiceContainer::getInstance()
                ->get('procedure_loader_factory')
                    ->createProcedureLoader( __DIR__ . "/../../../app/Resources/phantomjs" );

        $client = Client::getInstance();
        $client->setProcedure('capture');
        $client->getProcedureLoader()->addLoader($procedureLoader);

        $jsRequest = $client->getMessageFactory()->createCaptureRequest(
            $this->generateUrl('formula', array(), true).'?math='.urlencode($request->get('math'))
        );

        $fileName = str_replace('\\','/',tempnam(sys_get_temp_dir(), 'render'));
        $jsRequest->setOutputFile($fileName);

        $client->send($jsRequest, $client->getMessageFactory()->createResponse());
        $this->cropImage($fileName);

        $response = new BinaryFileResponse($fileName);
        $response->deleteFileAfterSend(true);
        return $response;
    }

    protected function cropImage( $filePath )
    {
        if ( filesize($filePath) < 1 ) {
            $img = imagecreate(1, 1);
            imagefill($img, 1, 1, imagecolorallocate($img, 255, 0, 0));
            imagepng($img, $filePath);
            imagedestroy($img);
            return;
        }

        $img = imagecreatefrompng($filePath);
        $box = $this->imageTrimBox($img);

        // copy cropped portion
        $img2 = imagecreate($box['w'], $box['h']);
        imagecopy($img2, $img, 0, 0, $box['l'], $box['t'], $box['w'], $box['h']);
        imagepng($img2, $filePath);

        imagedestroy($img);
        imagedestroy($img2);
    }

    protected function imageTrimBox ($img, $hex=null)
    {
        if (!ctype_xdigit($hex)) $hex = imagecolorat($img, 0,0);
        $b_top = $b_lft = 0;
        $b_rt = $w1 = $w2 = imagesx($img);
        $b_btm = $h1 = $h2 = imagesy($img);

        do {
            //top
            for(; $b_top < $h1; ++$b_top) {
                for($x = 0; $x < $w1; ++$x) {
                    if(imagecolorat($img, $x, $b_top) != $hex) {
                        break 2;
                    }
        }
        }

        // stop if all pixels are trimmed
        if ($b_top == $b_btm) {
            $b_top = 0;
            $code = 2;
            break 1;
        }

        // bottom
        for(; $b_btm >= 0; --$b_btm) {
            for($x = 0; $x < $w1; ++$x) {
                if(imagecolorat($img, $x, $b_btm-1) != $hex) {
                    break 2;
                }
            }
        }

        // left
        for(; $b_lft < $w1; ++$b_lft) {
            for($y = $b_top; $y <= $b_btm; ++$y) {
                if(imagecolorat($img, $b_lft, $y) != $hex) {
                    break 2;
                }
            }
        }

        // right
        for(; $b_rt >= 0; --$b_rt) {
            for($y = $b_top; $y <= $b_btm; ++$y) {
                if(imagecolorat($img, $b_rt-1, $y) != $hex) {
                    break 2;
                }
            }

        }

        $w2 = $b_rt - $b_lft;
        $h2 = $b_btm - $b_top;
        $code = ($w2 < $w1 || $h2 < $h1) ? 1 : 0;
        } while (0);

        // result codes:
        // 0 = Trim Zero Pixels
        // 1 = Trim Some Pixels
        // 2 = Trim All Pixels
        return array(
            '#'     => $code,   // result code
            'l'     => $b_lft,  // left
            't'     => $b_top,  // top
            'r'     => $b_rt,   // right
            'b'     => $b_btm,  // bottom
            'w'     => $w2,     // new width
            'h'     => $h2,     // new height
            'w1'    => $w1,     // original width
            'h1'    => $h1,     // original height
        );
    }
}
