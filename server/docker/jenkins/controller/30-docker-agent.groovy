import hudson.model.Node
import hudson.slaves.DumbSlave
import hudson.slaves.JNLPLauncher
import hudson.slaves.RetentionStrategy
import java.nio.file.Files
import java.nio.file.StandardCopyOption
import jenkins.model.Jenkins

def controller = Jenkins.get()
def agentName = 'docker-local'
def agent = controller.getNode(agentName)

if (agent == null) {
    def launcher = new JNLPLauncher()
    launcher.setWebSocket(true)
    agent = new DumbSlave(agentName, '/home/jenkins/agent', launcher)
    agent.setNodeDescription('Local Linux Docker agent for Lernumi build and test jobs')
    agent.setNumExecutors(1)
    agent.setLabelString('docker-local')
    agent.setMode(Node.Mode.EXCLUSIVE)
    agent.setRetentionStrategy(new RetentionStrategy.Always())
    controller.addNode(agent)
}

// Build steps run on the agent; the controller only coordinates jobs.
controller.setNumExecutors(0)
controller.save()

// Preserve the administrator account/security settings and never log the secret.
def secret = new File('/run/jenkins-agent/secret')
def temporarySecret = new File('/run/jenkins-agent/secret.tmp')
temporarySecret.text = agent.toComputer().getJnlpMac()
temporarySecret.setReadable(false, false)
temporarySecret.setReadable(true, true)
temporarySecret.setWritable(false, false)
temporarySecret.setWritable(true, true)
Files.move(temporarySecret.toPath(), secret.toPath(),
    StandardCopyOption.REPLACE_EXISTING, StandardCopyOption.ATOMIC_MOVE)

println('Lernumi Docker agent registered: docker-local (1 executor)')
