<?php
/**
 * This file contains only the ProgramController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use AppBundle\Model\Program;
use AppBundle\Repository\ProgramRepository;
use AppBundle\Model\Organizer;
use AppBundle\Repository\OrganizerRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * The ProgramController handles listing, creating and editing programs.
 */
class ProgramController extends Controller
{
    /**
     * Display a list of the programs.
     * @Route("/programs", name="Programs")
     * @Route("/programs/", name="ProgramsSlash")
     * @return Response
     */
    public function indexAction()
    {
        $organizer = $this->getOrganizer();

        return $this->render('programs/index.html.twig', [
            'programs' => $organizer->getPrograms(),
            'gmTitle' => 'my-programs',
        ]);
    }

    /**
     * Show a form to create a new program.
     * @Route("/programs/new", name="NewProgram")
     * @Route("/programs/new/", name="NewProgramSlash")
     * @param Request $request The request object generated by Symfony.
     * @return Response|RedirectResponse
     */
    public function newAction(Request $request)
    {
        $organizer = $this->getOrganizer();
        $program = new Program($organizer);

        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($request, $program);
        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlash('success', /** @scrutinizer ignore-type */ [
                'program-created',
                $program->getDisplayTitle(),
            ]);
            return $form;
        }

        return $this->render('programs/new.html.twig', [
            'form' => $form->createView(),
            'program' => $program,
            'gmTitle' => 'add-new-program',
        ]);
    }

    /**
     * Show a form to edit the given program.
     * @Route("/programs/edit/{title}", name="EditProgram")
     * @Route("/programs/edit/{title}/", name="EditProgramSlash")
     * @param Request $request The request object generated by Symfony.
     * @param string $title Title of the program.
     * @return Response|RedirectResponse
     */
    public function editAction(Request $request, $title)
    {
        $em = $this->container->get('doctrine')->getManager();
        $program = $em->getRepository(Program::class)
            ->findOneBy(['title' => $title]);

        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($request, $program);
        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlash('success', /** @scrutinizer ignore-type */ [
                'program-updated',
                $program->getDisplayTitle(),
            ]);
            return $form;
        }

        return $this->render('programs/edit.html.twig', [
            'form' => $form->createView(),
            'program' => $program,
            'gmTitle' => $program->getDisplayTitle(),
            'organizers' => $program->getOrganizers(),
        ]);
    }

    /**
     * Delete a program.
     * @Route("/programs/delete/{title}", name="DeleteProgram")
     * @Route("/programs/delete/{title}/", name="DeleteProgramSlash")
     * @param  string $title
     * @return RedirectResponse
     */
    public function deleteAction($title)
    {
        $em = $this->container->get('doctrine')->getManager();
        $program = $em->getRepository(Program::class)
            ->findOneBy(['title' => $title]);

        // Flash message will be shown at the top of the page.
        $this->addFlash('danger', /** @scrutinizer ignore-type */ [
            'program-deleted',
            $program->getDisplayTitle(),
        ]);

        $em->remove($program);
        $em->flush();

        return $this->redirectToRoute('Programs');
    }

    /**
     * Show a specific program, listing all of its events.
     * @Route("/programs/{title}", name="Program")
     * @Route("/programs/{title}/", name="ProgramSlash")
     * @param string $title Title of the program.
     * @return Response
     */
    public function showAction($title)
    {
        $em = $this->container->get('doctrine')->getManager();
        $program = $em->getRepository(Program::class)
            ->findOneBy(['title' => $title]);

        return $this->render('programs/show.html.twig', [
            'program' => $program,
        ]);
    }

    /**
     * Get the organizer based on username stored in the session.
     * @return Organizer
     */
    private function getOrganizer()
    {
        $em = $this->container->get('doctrine')->getManager();
        $organizerRepo = new OrganizerRepository($em);
        $organizerRepo->setContainer($this->container);
        return $organizerRepo->getOrganizerByUsername(
            $this->get('session')->get('logged_in_user')->username
        );
    }

    /**
     * Handle creation or updating of a Program on form submission.
     * @param  Request $request The request object generated by Symfony.
     * @param  Program $program
     * @return Form|RedirectResponse
     */
    private function handleFormSubmission(Request $request, Program $program)
    {
        $form = $this->getFormForProgram($program);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $program = $form->getData();
            $em = $this->container->get('doctrine')->getManager();
            $em->persist($program);
            $em->flush();

            return $this->redirectToRoute('Programs');
        }

        return $form;
    }

    /**
     * Build a form for the given program.
     * @param  Program $program
     * @return Form
     */
    private function getFormForProgram(Program $program)
    {
        $builder = $this->createFormBuilder($program)
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ]
            ])
            ->add('organizers', CollectionType::class, [
                'entry_type' => TextType::class,
                'constraints' => new NotBlank(),
                'allow_add' => true,
                'allow_delete' => true,
            ])
            ->add('submit', SubmitType::class);

        $builder->get('organizers')
            ->addModelTransformer($this->getCallbackTransformer());

        return $builder->getForm();
    }

    /**
     * Transform organizer data to or from the form.
     * This essentially pulls in the username from the user ID,
     * and sets the user ID before persisting so that the username
     * can be validated.
     * @return CallbackTransformer
     */
    private function getCallbackTransformer()
    {
        return new CallbackTransformer(
            function ($organizerObjects) {
                return array_map(function ($organizer) {
                    return $organizer->getUsername();
                }, $organizerObjects->toArray());
            },
            function ($organizerNames) {
                return array_map(function ($organizerName) {
                    $em = $this->container->get('doctrine')->getManager();
                    $organizerRepo = new OrganizerRepository($em);
                    $organizerRepo->setContainer($this->container);
                    return $organizerRepo->getOrganizerByUsername($organizerName);
                }, $organizerNames);
            }
        );
    }
}
